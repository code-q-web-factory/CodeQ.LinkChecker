<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Command;

use CodeQ\LinkChecker\Domain\Crawler\ContentNodeCrawler;
use CodeQ\LinkChecker\Domain\Service\DomainService;
use CodeQ\LinkChecker\Infrastructure\UriFactory;
use CodeQ\LinkChecker\Profile\CheckAllLinks;
use CodeQ\LinkChecker\Reporter\LogBrokenLinks;
use CodeQ\LinkChecker\Domain\Notification\NotificationServiceInterface;
use GuzzleHttp\RequestOptions;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Http\BaseUriProvider;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Neos\Domain\Model\Domain;
use Neos\Utility\ObjectAccess;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\Crawler;

/**
 * @Flow\Scope("singleton")
 * @see https://gist.github.com/hhoechtl/9012d455eab52658bbf4
 */
class CheckLinksCommandController extends CommandController
{
    public const MIN_STATUS_CODE = 404;

    /**
     * @var Translator
     * @Flow\Inject
     */
    protected $translator;

    /**
     * @var DomainService
     * @Flow\Inject
     */
    protected $domainService;

    /**
     * @var ContextFactoryInterface
     * @Flow\Inject
     */
    protected $contextFactory;

    /**
     * @var ContentNodeCrawler
     * @Flow\Inject
     */
    protected $contentNodeCrawler;

    /**
     * @var UriFactory
     * @Flow\Inject
     */
    protected $uriFactory;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="notifications.service")
     */
    protected $notificationServiceClass;

    /**
     * @var BaseUriProvider
     * @Flow\Inject(lazy=false)
     */
    protected $baseUriProvider;

    protected array $settings;

    /**
     * Inject the settings
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    private function legacyHackPrettyUrls(): void
    {
        // with Flow 7.1 not needed anymore
        // see FEATURE: Enable URL Rewriting by default
        // https://github.com/neos/flow-development-collection/pull/2459
        // needed for \CodeQ\LinkChecker\Domain\Factory\UriBuilderFactory::create
        if ($_SERVER['FLOW_REWRITEURLS'] !== '1') {
            $_SERVER['FLOW_REWRITEURLS'] = '1';
        }
    }

    /**
     * Crawl for invalid node links and external links
     *
     * @throws \Neos\Flow\Security\Exception
     * @throws UnresolvedDependenciesException
     * @throws InvalidArgumentTypeException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws InvalidArgumentNameException
     * @throws \Neos\Neos\Exception
     * @throws MissingActionNameException
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidQueryException
     * @throws InvalidControllerNameException
     */
    public function crawlCommand(): void
    {
        $this->legacyHackPrettyUrls();

        $this->crawlNodesCommand();
        $this->crawlExternalLinksCommand();
    }

    /**
     * Crawl for invalid external links
     *
     * This command crawls the whole website for invalid external links
     */
    public function crawlExternalLinksCommand(): void
    {
        $crawlProfile = new CheckAllLinks();
        $crawlObserver = new LogBrokenLinks();
        $clientOptions = $this->getClientOptions();

        $crawler = Crawler::create($clientOptions)
            ->setConcurrency($this->getConcurrency())
            ->setCrawlObserver($crawlObserver)
            ->setCrawlProfile($crawlProfile);

        if ($this->shouldIgnoreRobots()) {
            $crawler->ignoreRobots();
        }

        /** @var non-empty-string[] $urlsToCrawl */
        $urlsToCrawl = $this->settings['urlsToCrawl'];

        foreach ($urlsToCrawl as $url) {
            try {
                $this->outputLine("Start scanning {$url}");
                $this->outputLine('');

                $crawler->startCrawling($url);

                if ($this->settings['notifications']['enabled'] ?? false) {
                    $this->sendNotification($crawlObserver->getResultItemsGroupedByStatusCode());
                }
            } catch (\InvalidArgumentException $exception) {
                $this->outputLine('ERROR:  ' . $exception->getMessage());
            }
        }
    }

    /**
     * Get client options for the guzzle client from the settings. If no settings are configured we just set
     * timeout and allow_redirect.
     *
     * @return array
     */
    protected function getClientOptions(): array
    {
        $clientOptions = [
            RequestOptions::TIMEOUT => 100,
            RequestOptions::ALLOW_REDIRECTS => false,
        ];

        $optionsSettings = $this->settings['clientOptions'] ?? [];
        if (isset($optionsSettings['cookies']) && is_bool($optionsSettings['cookies'])) {
            $clientOptions[RequestOptions::COOKIES] = $optionsSettings['cookies'];
        }

        if (isset($optionsSettings['connectionTimeout']) && is_numeric($optionsSettings['connectionTimeout'])) {
            $clientOptions[RequestOptions::CONNECT_TIMEOUT] = $optionsSettings['connectionTimeout'];
        }

        if (isset($optionsSettings['timeout']) && is_numeric($optionsSettings['timeout'])) {
            $clientOptions[RequestOptions::TIMEOUT] = $optionsSettings['timeout'];
        }

        if (isset($optionsSettings['allowRedirects']) && is_bool($optionsSettings['allowRedirects'])) {
            $clientOptions[RequestOptions::ALLOW_REDIRECTS] = $optionsSettings['allowRedirects'];
        }

        if (
            isset($optionsSettings['auth']) && is_array($optionsSettings['auth'])
            && count($optionsSettings['auth']) > 1
        ) {
            $clientOptions[RequestOptions::AUTH] = $optionsSettings['auth'];
        }

        return $clientOptions;
    }

    /**
     * Returns concurrency. If not found, simply returns a default value like
     * 10 (default).
     */
    protected function getConcurrency(): int
    {
        if (isset($this->settings['concurrency']) && (int)$this->settings['concurrency'] >= 0) {
            return (int)$this->settings['concurrency'];
        }

        return 10;
    }

    /**
     * Returns true by default and can be changed by the setting ignoreRobots
     */
    protected function shouldIgnoreRobots(): bool
    {
        return !isset($this->settings['ignoreRobots']) || $this->settings['ignoreRobots'];
    }

    /**
     * Send notification about the result of the link check run. The notification service can be configured.
     * Default is the emailService.
     */
    protected function sendNotification(array $results): void
    {
        $notificationServiceClass = trim($this->notificationServiceClass);
        if ($notificationServiceClass === '') {
            $errorMessage = 'No notification service has been configured, but the notification handling is enabled';
            throw new \InvalidArgumentException($errorMessage, 1540201992);
        }

        $minimumStatusCode = $this->settings['notifications']['minimumStatusCode'] ?? self::MIN_STATUS_CODE;
        $arguments = [];
        foreach ($results as $statusCode => $urls) {
            if ($statusCode < (int)$minimumStatusCode) {
                continue;
            }
            $arguments['result'][$statusCode] = [
                'statusCode' => $statusCode,
                'urls' => $urls,
                'amount' => count($urls)
            ];
        }

        $notificationService = $this->objectManager->get($notificationServiceClass);

        if (!$notificationService instanceof NotificationServiceInterface) {
            throw new \InvalidArgumentException(
                "NotificationService $notificationServiceClass, doesnt implement the NotificationServiceInterface",
                1668164428
            );
        }
        $notificationService->sendNotification($this->settings['notifications']['subject'] ?? '', $arguments);
    }

    /**
     * Crawl for invalid links within nodes
     *
     * This command crawls an url for invalid internal and external links
     *
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws InvalidQueryException
     * @throws MissingActionNameException
     * @throws UnresolvedDependenciesException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    public function crawlNodesCommand(): void
    {
        /** @var non-empty-string[] $urlsToCrawl */
        $urlsToCrawl = $this->settings['urlsToCrawl'];

        $domainsToCrawl = $this->domainService->getDomainsToCrawl($urlsToCrawl);

        if (count($domainsToCrawl) === 0) {
            $message = $this->translator->translatebyid('noDomainsFound', [], null, null, 'Modules', 'CodeQ.LinkChecker');
            $this->output->outputFormatted('<error>' . $message . '</error>');
        }

        foreach ($domainsToCrawl as $domainToCrawl) {
            $baseUriOfDomain = $this->uriFactory->createFromDomain($domainToCrawl);
            $this->hackTheConfiguredBaseUriOfTheBaseUriProviderSingleton($baseUriOfDomain);
            $this->crawlDomain($domainToCrawl);
        }
    }

    private function hackTheConfiguredBaseUriOfTheBaseUriProviderSingleton(UriInterface $baseUri): void
    {
        assert($this->baseUriProvider instanceof BaseUriProvider);
        ObjectAccess::setProperty($this->baseUriProvider, "configuredBaseUri", (string)$baseUri, true);
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws UnresolvedDependenciesException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    protected function crawlDomain(Domain $domain): void
    {
        $context = $this->contextFactory->create([
            'currentSite' => $domain->getSite(),
            'currentDomain' => $domain,
        ]);

        $messages = $this->contentNodeCrawler->crawl($context, $domain);

        foreach ($messages as $message) {
            $this->output->outputFormatted('<error>' . $message . '</error>');
        }
    }
}

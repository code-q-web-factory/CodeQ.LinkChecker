<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Command;

use CodeQ\LinkChecker\Domain\Crawler\ContentNodeCrawler;
use CodeQ\LinkChecker\Domain\Model\ResultItemRepositoryInterface;
use CodeQ\LinkChecker\Infrastructure\DomainService;
use CodeQ\LinkChecker\Infrastructure\LogAndPersistResultCrawlObserver;
use CodeQ\LinkChecker\Infrastructure\UriFactory;
use CodeQ\LinkChecker\Infrastructure\CrawlNonExcludedUrls;
use CodeQ\LinkChecker\Domain\Notification\NotificationServiceInterface;
use CodeQ\LinkChecker\Infrastructure\OriginUrlException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Http\BaseUriProvider;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Utility\ObjectAccess;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\Crawler;

/**
 * @Flow\Scope("singleton")
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
     * @Flow\Inject
     * @var ResultItemRepositoryInterface
     */
    protected $resultItemRepository;

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

    /**
     * Clear all stored errors
     *
     * @param bool $keepIgnored ignored errors will not be deleted
     */
    public function clearCommand(bool $keepIgnored = false): void
    {
        if ($keepIgnored) {
            $this->resultItemRepository->removeAllNonIgnored();
        } else {
            $this->resultItemRepository->truncate();
        }
    }

    /**
     * Crawl for invalid node links and external links
     *
     * @param bool $withNotification sends email notification after scan
     */
    public function crawlCommand(bool $withNotification = false): void
    {
        $this->legacyHackPrettyUrls();
        $domainsToCrawl = $this->domainService->findAllSitesPrimaryDomain();
        $this->ensureDomainsNotEmpty($domainsToCrawl);
        $errorCount = 0;
        $this->crawlNodesCommandImplementation($domainsToCrawl, $errorCount);
        $this->crawlExternalCommandImplementation($domainsToCrawl, $errorCount);
        if ($withNotification) {
            $this->sendNotificationIfNecessary($errorCount, $this->createLinkCheckerDashboardUriFromStuff($domainsToCrawl));
        }
    }

    /**
     * Crawl for invalid links within nodes
     *
     * This command crawls an url for invalid internal and external links
     *
     * @param bool $withNotification sends email notification after scan
     */
    public function crawlNodesCommand(bool $withNotification = false): void
    {
        $this->legacyHackPrettyUrls();
        $domainsToCrawl = $this->domainService->findAllSitesPrimaryDomain();
        $this->ensureDomainsNotEmpty($domainsToCrawl);
        $errorCount = 0;
        $this->crawlNodesCommandImplementation($domainsToCrawl, $errorCount);
        if ($withNotification) {
            $this->sendNotificationIfNecessary($errorCount, $this->createLinkCheckerDashboardUriFromStuff($domainsToCrawl));
        }
    }

    /**
     * Crawl for invalid external links
     *
     * This command crawls the whole website for invalid external links
     *
     * @param bool $withNotification sends email notification after scan
     */
    public function crawlExternalLinksCommand(bool $withNotification = false): void
    {
        $this->legacyHackPrettyUrls();
        $domainsToCrawl = $this->domainService->findAllSitesPrimaryDomain();
        $this->ensureDomainsNotEmpty($domainsToCrawl);
        $errorCount = 0;
        $this->crawlExternalCommandImplementation($domainsToCrawl, $errorCount);
        if ($withNotification) {
            $this->sendNotificationIfNecessary($errorCount, $this->createLinkCheckerDashboardUriFromStuff($domainsToCrawl));
        }
    }

    private function crawlNodesCommandImplementation(array $domainsToCrawl, int &$errorCount): void
    {
        foreach ($domainsToCrawl as $domainToCrawl) {
            $baseUriOfDomain = $this->uriFactory->createFromDomain($domainToCrawl);
            $this->hackTheConfiguredBaseUriOfTheBaseUriProviderSingleton($baseUriOfDomain);

            /** @var ContentContext $subgraph */
            $subgraph = $this->contextFactory->create([
                'currentSite' => $domainToCrawl->getSite(),
                'currentDomain' => $domainToCrawl,
            ]);

            $messages = $this->contentNodeCrawler->crawl($subgraph, $domainToCrawl);
            $errorCount += \count($messages);

            foreach ($messages as $message) {
                $this->output->outputFormatted('<error>' . $message . '</error>');
            }
            $this->output->outputLine("Problems: " . \count($messages));
        }
    }

    private function crawlExternalCommandImplementation(array $domainsToCrawl, int &$errorCount): void
    {
        $crawlProfile = new CrawlNonExcludedUrls();
        $crawlObserver = new LogAndPersistResultCrawlObserver();

        $crawler = self::createCrawlerFromSettings($this->settings['clientOptions'] ?? [])
            ->setConcurrency($this->getConcurrency())
            ->setCrawlObserver($crawlObserver)
            ->setCrawlProfile($crawlProfile);

        if ($this->shouldIgnoreRobots()) {
            $crawler->ignoreRobots();
        }

        foreach ($domainsToCrawl as $domainToCrawl) {

            $url = $this->uriFactory->createFromDomain($domainToCrawl);

            try {
                $this->outputLine("Start scanning $url");
                $this->outputLine('');

                try {
                    $crawler->startCrawling($url);
                    $errorCount += $crawlObserver->getErrorCount();
                } catch (OriginUrlException $originUrlException) {
                    $this->outputFormatted("<error>{$originUrlException->getMessage()}</error>");
                    $this->outputFormatted("<error>The configured site domain $url could not be reached, please check if the URL is correct.</error>");
                    return;
                }

            } catch (\InvalidArgumentException $exception) {
                $this->outputLine('ERROR:  ' . $exception->getMessage());
            }
        }
    }

    /** @throws StopCommandException */
    private function ensureDomainsNotEmpty(array $domains): void
    {
        if (count($domains) == 0) {
            $message = $this->translator->translatebyid('noDomainsFound', [], null, null, 'Modules', 'CodeQ.LinkChecker');
            $this->output->outputFormatted('<error>' . $message . '</error>');
            $this->quit();
        }
    }

    private static function createCrawlerFromSettings(array $settings): Crawler
    {
        // If no settings are configured we just set timeout and allow_redirect.
        $clientOptions = [
            RequestOptions::TIMEOUT => 100,
            RequestOptions::ALLOW_REDIRECTS => false,
        ];

        if (isset($settings['cookies']) && is_bool($settings['cookies'])) {
            $clientOptions[RequestOptions::COOKIES] = $settings['cookies'];
        }

        if (isset($settings['connectionTimeout']) && is_numeric($settings['connectionTimeout'])) {
            $clientOptions[RequestOptions::CONNECT_TIMEOUT] = (int)$settings['connectionTimeout'];
        }

        if (isset($settings['timeout']) && is_numeric($settings['timeout'])) {
            $clientOptions[RequestOptions::TIMEOUT] = (int)$settings['timeout'];
        }

        if (isset($settings['allowRedirects']) && is_bool($settings['allowRedirects'])) {
            $clientOptions[RequestOptions::ALLOW_REDIRECTS] = $settings['allowRedirects'];
        }

        if (
            isset($settings['auth']) && is_array($settings['auth'])
            && count($settings['auth']) > 1
        ) {
            $clientOptions[RequestOptions::AUTH] = $settings['auth'];
        }

        $handler = HandlerStack::create();

        if (isset($settings['retryAttempts']) && is_numeric($settings['retryAttempts']) && $settings['retryAttempts'] >= 0) {

            $retryAttempts = (int)$settings['retryAttempts'];

            $handler->push(
                Middleware::retry(
                    function (
                        $retries,
                        Request $request,
                        Response $response = null,
                        \Exception $exception = null
                    ) use($retryAttempts) {
                        if ($retries >= $retryAttempts) {
                            return false;
                        }
                        if ($exception instanceof ConnectException) {
                            return true;
                        }
                        return false;
                    },
                    function (
                        $numberOfRetries
                    ) {
                        return 1000 * $numberOfRetries;
                    }
                )
            );
        }

        $clientOptions["handler"] = $handler;

        return Crawler::create($clientOptions);
    }

    /**
     * Returns concurrency. If not found, simply returns a default value like
     * 10 (default).
     */
    private function getConcurrency(): int
    {
        if (isset($this->settings['concurrency']) && (int)$this->settings['concurrency'] >= 0) {
            return (int)$this->settings['concurrency'];
        }

        return 10;
    }

    /**
     * Returns true by default and can be changed by the setting ignoreRobots
     */
    private function shouldIgnoreRobots(): bool
    {
        return !isset($this->settings['ignoreRobots']) || $this->settings['ignoreRobots'];
    }

    private function createLinkCheckerDashboardUriFromStuff(array $domains): UriInterface
    {
        $firstDomain = $domains[0];
        $baseUri = $this->uriFactory->createFromDomain($firstDomain);
        return $this->createBackendModuleUri("management/link-checker", "index", $baseUri);
    }

    private function createBackendModuleUri(string $module, string $moduleAction, UriInterface $baseUri): UriInterface
    {
        $request = new ServerRequest("GET", $baseUri);
        $actionRequest = Mvc\ActionRequest::fromHttpRequest($request);
        $uriBuilder = new Mvc\Routing\UriBuilder();
        $uriBuilder->setRequest($actionRequest);

        $uriBuilder->setCreateAbsoluteUri(true);

        return new Uri($uriBuilder->uriFor(
            'index',
            [
                'module' => $module,
                'moduleArguments' => ['@action' => $moduleAction]
            ],
            'Backend\Module',
            'Neos.Neos'
        ));
    }

    /**
     * Send notification about the result of the link check run. The notification service can be configured.
     * Default is the emailService.
     */
    private function sendNotificationIfNecessary(int $errorCount, UriInterface $linkCheckerDashboardUri): void
    {
        if ($errorCount <= 0) {
            return;
        }

        if (!$this->settings['notifications']['enabled']) {
            return;
        }

        $notificationServiceClass = trim($this->notificationServiceClass);
        if ($notificationServiceClass === '') {
            $errorMessage = 'No notification service has been configured, but the notification handling is enabled';
            throw new \InvalidArgumentException($errorMessage, 1540201992);
        }

        $notificationService = $this->objectManager->get($notificationServiceClass);

        if (!$notificationService instanceof NotificationServiceInterface) {
            throw new \InvalidArgumentException(
                "NotificationService $notificationServiceClass, doesnt implement the NotificationServiceInterface",
                1668164428
            );
        }
        $notificationService->sendNotification(
            $this->settings['notifications']['subject'] ?? '',
            [
                'errorCount' => $errorCount,
                'linkCheckerDashboardUri' => $linkCheckerDashboardUri
            ]
        );
    }

    private function hackTheConfiguredBaseUriOfTheBaseUriProviderSingleton(UriInterface $baseUri): void
    {
        assert($this->baseUriProvider instanceof BaseUriProvider);
        ObjectAccess::setProperty($this->baseUriProvider, "configuredBaseUri", (string)$baseUri, true);
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
}

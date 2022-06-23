<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Command;

use CodeQ\LinkChecker\Domain\Crawler\ContentNodeCrawler;
use CodeQ\LinkChecker\Domain\Service\DomainService;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Neos\Domain\Model\Domain;

/**
 * @Flow\Scope("singleton")
 * @see https://gist.github.com/hhoechtl/9012d455eab52658bbf4
 */
class CheckLinksCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var DomainService
     */
    protected $domainService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var ContentNodeCrawler
     */
    protected $contentNodeCrawler;

    protected array $settings;

    /**
     * Inject the settings
     *
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Crawl for invalid links
     *
     * This command crawls an url for invalid internal and external links
     *
     * @param string $url The url to start crawling from
     * @param int $concurrency Maximum number of requests to send concurrently
     * @return void
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws NodeConfigurationException
     * @throws UnresolvedDependenciesException
     * @throws \Neos\Eel\Exception
     * @throws InvalidQueryException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    public function crawlCommand(string $url = '', int $concurrency = 10): void
    {
        /** @var non-empty-string[] $urlsToCrawl */
        $urlsToCrawl = $this->settings['urlsToCrawl'];

        $domainsToCrawl = $this->domainService->getDomainsToCrawl($urlsToCrawl);

        foreach ($domainsToCrawl as $domainToCrawl) {
            $this->crawlDomain($domainToCrawl);
        }
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws NodeConfigurationException
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

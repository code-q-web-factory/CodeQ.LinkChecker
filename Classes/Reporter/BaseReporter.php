<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Reporter;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use CodeQ\LinkChecker\Service\UriService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\ConsoleOutput;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Spatie\Crawler\CrawlObserver;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

abstract class BaseReporter extends CrawlObserver
{
    /**
     * @Flow\Inject
     * @var ResultItemStorage
     */
    protected $resultItemStorage;

    /**
     * @Flow\Inject
     * @var ConsoleOutput
     */
    protected $output;

    /**
     * @Flow\InjectConfiguration(path="excludeStatusCodes")
     */
    protected array $excludeStatusCodes = [];

    protected array $resultItemsGroupedByStatusCode = [];

    public function getResultItemsGroupedByStatusCode(): array
    {
        return $this->resultItemsGroupedByStatusCode;
    }

    public function __construct()
    {
    }

    /**
     * Outputs specified text to the console window and appends a line break
     *
     * @see output()
     * @see outputLines()
     */
    protected function outputLine(string $text = '', array $arguments = []): void
    {
        $this->output->outputLine($text, $arguments);
    }

    /**
     * Called when the crawler has crawled the given url successfully.
     *
     * @return int
     */
    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null
    ) {
        $statusCode = $response->getStatusCode();
        if (!$this->isExcludedStatusCode($statusCode)) {
            $this->addCrawlingResultToStore($url, $foundOnUrl, $statusCode);
        }

        return $statusCode;
    }

    /**
     * Called when the crawler had a problem crawling the given url.
     */
    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null
    ): int {
        $statusCode = (int)$requestException->getCode();
        if (!$this->isExcludedStatusCode($statusCode)) {
            $this->addCrawlingResultToStore($url, $foundOnUrl, $statusCode);
        }

        return $statusCode;
    }

    /**
     * We collect the crawling results in the class variable urlsGroupedByStatusCode.
     * We store the crawled url, the status code for this url and if an origin url exists also the location where
     * we got the crawling url from.
     */
    protected function addCrawlingResultToStore(
        UriInterface $crawlingUrl,
        UriInterface $originUrl = null,
        int $statusCode = 200
    ): void {
        $cliMessage = "Checked {$crawlingUrl} from {$originUrl} with status {$statusCode}";
        if ($originUrl === null) {
            $cliMessage = "Checked {$crawlingUrl} with status {$statusCode}";
        }

        $this->outputLine($cliMessage);

        if ($statusCode === 200) {
            return;
        }

        $parts = parse_url((string)$originUrl);

        if ($parts === false) {
            return;
        }

        $linkCheckItem = new ResultItem();
        $linkCheckItem->setDomain($parts['host']);
        $linkCheckItem->setSourcePath(UriService::uriToString($originUrl));
        $linkCheckItem->setTarget(UriService::uriToString($crawlingUrl));
        $linkCheckItem->setStatusCode($statusCode);
        $linkCheckItem->setCreatedAt(new \DateTime());
        $linkCheckItem->setCheckedAt(new \DateTime());

        try {
            $this->resultItemStorage->add($linkCheckItem);
        } catch (IllegalObjectTypeException $e) {
            $this->outputLine("Could not persist entry for the url {$crawlingUrl}");
        }

        $this->resultItemsGroupedByStatusCode[$statusCode][] = $linkCheckItem;
    }

    /**
     * Determine if the status code concerns a successful or
     * redirect response.
     *
     * @param int|string $statusCode
     * @return bool
     */
    protected function isSuccessOrRedirect($statusCode): bool
    {
        return in_array((int)$statusCode, [200, 201, 301], true);
    }

    /**
     * Determine if the crawler saw some bad urls.
     */
    protected function crawledBadUrls(): bool
    {
        return collect($this->resultItemsGroupedByStatusCode)->keys()->filter(function ($statusCode) {
                return !$this->isSuccessOrRedirect($statusCode);
            })->count() > 0;
    }

    /**
     * Determine if the status code should be excluded'
     * from the reporter.
     *
     * @param int|string $statusCode
     * @return bool
     */
    protected function isExcludedStatusCode($statusCode): bool
    {
        return in_array($statusCode, $this->excludeStatusCodes, true);
    }
}

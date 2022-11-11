<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Profile;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlProfile as AbstractCrawlProfile;

class CrawlNonExcludedUrls extends AbstractCrawlProfile
{
    /**
     * @var array
     * @Flow\InjectConfiguration(path="excludeUrls")
     */
    protected $excludeUrlRegexPatterns = [];

    public function shouldCrawl(UriInterface $url): bool
    {
        foreach ($this->excludeUrlRegexPatterns as $excludeUrlRegexPattern) {
            $match = preg_match($excludeUrlRegexPattern, (string)$url);
            switch ($match) {
                case 0:
                    break;
                case 1:
                    return false;
                case false:
                    throw new \RuntimeException('Invalid regex pattern: ' . $excludeUrlRegexPattern, 1668185080);
            }
        }
        return true;
    }
}

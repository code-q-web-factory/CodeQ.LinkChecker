<?php

namespace CodeQ\LinkChecker\Service;

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * @Flow\Scope("singleton")
 */
class UriService
{
    /**
     * Generates a string for an uri that implements the psr 7 uri interface.
     */
    public static function uriToString(UriInterface $uri = null): string
    {
        if ($uri === null) {
            return '';
        }

        return Uri::composeComponents(
            $uri->getScheme(),
            $uri->getAuthority(),
            $uri->getPath(),
            $uri->getQuery(),
            $uri->getFragment()
        );
    }
}

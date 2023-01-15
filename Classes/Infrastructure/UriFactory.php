<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Infrastructure;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\Domain;
use Psr\Http\Message\UriInterface;

/**
 * @Flow\Scope("singleton")
 */
class UriFactory
{
    public function createFromDomain(Domain $domain): UriInterface
    {
        return new Uri($domain->__toString());
    }
}

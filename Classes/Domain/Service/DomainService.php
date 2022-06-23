<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Repository\DomainRepository;

/**
 * @Flow\Scope("singleton")
 */
class DomainService
{
    /**
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * @return Domain[]
     * @throws InvalidQueryException
     */
    public function getDomainsToCrawl(array $urls)
    {
        $domainCollection = $this->createDomainCollection($urls);

        $query = $this->domainRepository->createQuery();
        /** @var Domain[] $domains */
        $domains = $query
            ->matching(
                $query->in(
                    'hostname',
                    array_map(static fn (Domain $domain) => $domain->getHostname(), $domainCollection)
                )
            )
            ->execute()
            ->toArray();
        return $domains;
    }

    /**
     * @param array $urls
     * @return Domain[]
     */
    protected function createDomainCollection(array $urls): array
    {
        $domains = [];
        foreach ($urls as $url) {
            $parts = parse_url($url);
            if ($parts === false) {
                continue;
            }

            $domain = new Domain();
            $domain->setScheme($parts['scheme']);
            $domain->setHostname($parts['host']);
            $domains[] = $domain;
        }

        // Filter duplicate domains by hostname, kind of like array_unique for objects
        return array_filter($domains, static function (Domain $domain) {
            static $hostNames = [];
            $hostName = $domain->getHostname();
            if (in_array($hostName, $hostNames, true)) {
                return false;
            }
            $hostNames[] = $hostName;
            return true;
        });
    }
}

<?php

namespace CodeQ\LinkChecker\Infrastructure;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;

class DomainService
{
    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @return Domain[]
     */
    public function findAllSitesPrimaryDomain()
    {
        /** @var Site[] $sites */
        $sites = $this->siteRepository->findAll()->toArray();
        $domainsWithUniqueSite = [];
        foreach ($sites as $site) {
            $domainsWithUniqueSite[] = $site->getPrimaryDomain();
        }
        return $domainsWithUniqueSite;
    }
}

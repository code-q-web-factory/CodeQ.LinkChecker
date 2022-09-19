<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Repository;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;

/**
 * @Flow\Scope("singleton")
 */
class ResultItemRepository extends Repository
{
    public function findOneByData(array $properties = [], $cacheResult = false): ?ResultItem
    {
        $query = $this->createQuery();
        $constraints = [];
        foreach ($properties as $propertyName => $propertyValue) {
            $constraints[] = $query->equals($propertyName, $propertyValue);
        }
        return $query
            ->matching($query->logicalAnd($constraints))
            ->execute($cacheResult)
            ->getFirst();
    }
}

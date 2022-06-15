<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Storage;

use Doctrine\ORM\Internal\Hydration\IterableResult;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\Flow\Annotations as Flow;

class NodeDataStorage
{
    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    public function findAll(): IterableResult
    {
        return $this->nodeDataRepository->findAllIterator();
    }
}

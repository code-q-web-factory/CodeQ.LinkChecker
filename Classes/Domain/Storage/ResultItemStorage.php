<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Storage;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Repository\ResultItemRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\QueryResultInterface;

class ResultItemStorage
{
    /**
     * @Flow\Inject
     *
     * @var ResultItemRepository
     */
    protected $resultItemRepository;

    public function findAll(): QueryResultInterface
    {
        return $this->resultItemRepository->findAll();
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function remove(ResultItem $resultItem): void
    {
        $this->resultItemRepository->remove($resultItem);
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function ignore(ResultItem $resultItem): void
    {
        $resultItem->setIgnore(true);

        $this->resultItemRepository->update($resultItem);
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function add(ResultItem $resultItem): void
    {
        $existingResultItem = $this->resultItemRepository->findOneByData([
            'domain' => $resultItem->getDomain(),
            'source' => $resultItem->getSource(),
            'target' => $resultItem->getTarget(),
            'statusCode' => $resultItem->getStatusCode(),
        ]);

        if ($existingResultItem instanceof ResultItem) {
            return;
        }

        $this->resultItemRepository->add($resultItem);
    }
}

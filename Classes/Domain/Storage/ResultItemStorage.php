<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Storage;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Repository\ResultItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\QueryResultInterface;

class ResultItemStorage
{
    /**
     * @Flow\Inject
     *
     * @var ResultItemRepository
     */
    protected $resultItemRepository;

    /**
     * Doctrine's Entity Manager.
     *
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function findAll(): QueryResultInterface
    {
        $query = $this->resultItemRepository->createQuery();
        $query->matching($query->equals('ignore', 0));
        $query->setOrderings(
            [
                'source' => QueryInterface::ORDER_ASCENDING,
            ]
        );
        return $query->execute();
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function remove(ResultItem $resultItem): void
    {
        $this->resultItemRepository->remove($resultItem);
    }

    public function truncate(): void
    {
        $qB = $this->entityManager->createQueryBuilder()
            ->delete(ResultItem::class);

        $query = $qB->getQuery();
        $query->execute();
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

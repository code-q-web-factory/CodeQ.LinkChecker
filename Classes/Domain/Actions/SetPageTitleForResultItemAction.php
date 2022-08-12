<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Actions;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\LinkingService;

class SetPageTitleForResultItemAction
{
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @throws NodeException
     */
    public function execute(ResultItem $resultItem, string $target): void
    {
        preg_match(LinkingService::PATTERN_SUPPORTED_URIS, $target, $matches);
        $nodeIdentifier = $matches[2];

        $baseContext = $this->createContext('live', []);
        $targetNode = $baseContext->getNodeByIdentifier($nodeIdentifier);

        if (!($targetNode instanceof NodeInterface)) {
            return;
        }

        $resultItem->setTargetPageTitle($targetNode->getProperty('title'));
    }

    private function createContext(string $workspaceName, array $dimensions): Context
    {
        return $this->contextFactory->create([
            'workspaceName' => $workspaceName,
            'dimensions' => $dimensions,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);
    }
}

<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Crawler;

use CodeQ\LinkChecker\Domain\Model\ResultItemRepositoryInterface;
use CodeQ\LinkChecker\Domain\Model\ResultItem;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\RouterInterface;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Service\LinkingService;

class ContentNodeCrawler
{
    /**
     * Pattern to match telephone numbers.
     *
     * @var string
     */
    protected const PATTERN_SUPPORTED_PHONE_NUMBERS = '/href="(tel):(\+?\d*)/';

    /**
     * @var ResultItemRepositoryInterface
     * @Flow\Inject
     */
    protected $resultItemRepository;

    /**
     * @var ContextFactoryInterface
     * @Flow\Inject
     */
    protected $contextFactory;

    /**
     * @var LinkingService
     * @Flow\Inject
     */
    protected $linkingService;

    /**
     * @var RouterInterface
     * @Flow\Inject
     */
    protected $router;

    /**
     * @var NodeDataRepository
     * @Flow\Inject
     */
    protected $nodeDataRepository;

    public function crawl(ContentContext $subgraph, Domain $domain): array
    {
        /** @var Node[] $allContentAndDocumentNodes */
        $allContentAndDocumentNodes = FlowQuery::q([$subgraph->getCurrentSiteNode()])
            ->find('[instanceof Neos.Neos:Document],[instanceof Neos.Neos:Content]')->get();

        $messages = [];

        foreach ($allContentAndDocumentNodes as $node) {
            if (!$this->findIsNodeVisible($node)) {
                continue;
            }

            $unresolvedUris = [];
            $invalidPhoneNumbers = [];

            // todo why use nodeData here and not the node?
            $properties = $node->getNodeData()->getProperties();

            foreach ($properties as $property) {
                $this->crawlPropertyForNodesAndAssets($property, $subgraph, $unresolvedUris);
                $this->crawlPropertyForTelephoneNumbers($property, $invalidPhoneNumbers);
            }

            foreach ($unresolvedUris as $uri) {
                $messages[] = 'Not found: ' . $uri;

                $this->createResultItem($subgraph, $domain, $node, $uri, 404);
            }
            foreach ($invalidPhoneNumbers as $phoneNumber) {
                $messages[] = 'Invalid format: ' . $phoneNumber;

                /* @see https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml - 490 is unassigned, and so we can use it */
                $this->createResultItem($subgraph, $domain, $node, $phoneNumber, 490);
            }
        }

        return $messages;
    }

    /**
     * @see \Neos\Neos\Fusion\ConvertUrisImplementation::evaluate
     */
    protected function crawlPropertyForNodesAndAssets(
        $property,
        ContentContext $subgraph,
        array &$unresolvedUris
    ): void {
        if (!is_string($property)) {
            return;
        }

        if (!str_contains($property, 'node://') && !str_contains($property, 'asset://')) {
            return;
        }

        preg_replace_callback(
            LinkingService::PATTERN_SUPPORTED_URIS,
            function (array $matches) use (
                $subgraph,
                &$unresolvedUris
            ) {
                $type = $matches[1];
                $identifier = $matches[0];
                $targetIsVisible = false;
                switch ($type) {
                    case 'node':
                        $linkedNodeId = NodeAggregateIdentifier::fromString(
                            str_replace('node://', '', $identifier)
                        );
                        $linkedNode = $subgraph->getNodeByIdentifier((string)$linkedNodeId);
                        $targetIsVisible = $linkedNode && $this->findIsNodeVisible($linkedNode);
                        break;
                    case 'asset':
                        $targetIsVisible = $this->linkingService->resolveAssetUri($identifier) !== null;
                        break;
                }

                if ($targetIsVisible === false) {
                    $unresolvedUris[] = $identifier;
                }

                return "";
            },
            $property
        );
    }

    private function crawlPropertyForTelephoneNumbers(
        $property,
        array &$invalidPhoneNumbers
    ): void {
        if (!is_string($property)) {
            return;
        }

        if (!str_contains($property, 'tel:')) {
            return;
        }

        preg_replace_callback(
            self::PATTERN_SUPPORTED_PHONE_NUMBERS,
            static function (array $matches) use (&$invalidPhoneNumbers) {
                if ($matches[1] === 'tel') {
                    $resolvedUri = str_starts_with($matches[2], '+') ? $matches[2] : null;
                } else {
                    $resolvedUri = null;
                }

                if ($resolvedUri === null) {
                    $invalidPhoneNumbers[] = 'tel:' . $matches[2];
                    return $matches[0];
                }

                return $resolvedUri;
            },
            $property
        );
    }

    protected function createResultItem(
        ContentContext $subgraph,
        Domain $domain,
        Node $node,
        string $uri,
        int $statusCode
    ): void {
        $documentNode = $this->findClosestDocumentNode($node);

        $resultItem = new ResultItem();
        $resultItem->setDomain($domain->getHostname());
        $resultItem->setSource((string)$documentNode->getNodeAggregateIdentifier());
        $resultItem->setSourcePath((string)$documentNode->findNodePath());
        $resultItem->setTarget($uri);

        if (str_starts_with($uri, 'node://')) {
            $subgraphWithHiddenNodes = $this->subgraphWithConfiguration($subgraph, [
                'invisibleContentShown' => true,
                'inaccessibleContentShown' => true
            ]);

            $targetNodeId = NodeAggregateIdentifier::fromString(
                str_replace('node://', '', $uri)
            );
            $targetNode = $subgraphWithHiddenNodes->getNodeByIdentifier((string)$targetNodeId);
            if ($targetNode) {
                $resultItem->setTargetPath((string)$targetNode->findNodePath());
            }
        }

        $resultItem->setStatusCode($statusCode);
        $resultItem->setCreatedAt($subgraph->getCurrentDateTime());
        $resultItem->setCheckedAt($subgraph->getCurrentDateTime());

        $this->resultItemRepository->add($resultItem);
    }

    private function findClosestDocumentNode(Node $node): Node
    {
        while ($node->getNodeType()->isOfType('Neos.Neos:Document') === false) {
            $node = $node->findParentNode();
        }
        return $node;
    }

    private function findIsNodeVisible(Node $node): bool
    {
        do {
            $previousNode = $node;
            $node = $node->getParent();
            if ($node === null) {
                if ($previousNode->isRoot()) {
                    return true;
                }
                return false;
            }
        } while (true);
    }

    private function subgraphWithConfiguration(ContentContext $currentSubgraph, array $additionalConfiguration): ContentContext
    {
        $currentConfiguration = $currentSubgraph->getProperties();
        /** @var ContentContext $newSubgraph */
        $newSubgraph = $this->contextFactory->create(array_merge($currentConfiguration, $additionalConfiguration));
        return $newSubgraph;
    }
}

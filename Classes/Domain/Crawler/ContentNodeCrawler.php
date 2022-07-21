<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Crawler;

use CodeQ\LinkChecker\Domain\Factory\ControllerContextFactory;
use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Service\LinkingService;

class ContentNodeCrawler
{
    /**
     * @Flow\Inject
     * @var ResultItemStorage
     */
    protected $resultItemStorage;

    /**
     * @Flow\Inject
     * @var ControllerContextFactory
     */
    protected $controllerContextFactory;

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @throws \Neos\Eel\Exception
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws UnresolvedDependenciesException
     * @throws IllegalObjectTypeException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    public function crawl(Context $context, Domain $domain): array
    {
        /** @var Node[] $node */
        $allContentNodes = (new FlowQuery([$context->getCurrentSiteNode()]))->find('[instanceof Neos.Neos:Content]')->get();

        $messages = [];

        foreach ($allContentNodes as $node) {
            $nodeData = $node->getNodeData();

            $unresolvedUris = [];
            $controllerContext = $this->controllerContextFactory->create($domain);

            $properties = $nodeData->getProperties();

            foreach ($properties as $property) {
                $this->crawlProperty($property, $node, $controllerContext, $unresolvedUris);
            }

            foreach ($unresolvedUris as $uri) {
                $messages[] = 'Not found: ' . $uri;

                $this->createResultItem($context, $domain, $node, $uri);
            }
        }

        return $messages;
    }

    /**
     * @throws MissingActionNameException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     * @see \Neos\Neos\Fusion\ConvertUrisImplementation::evaluate
     */
    protected function crawlProperty(
        $property,
        NodeInterface $node,
        ControllerContext $controllerContext,
        array &$unresolvedUris
    ): void {
        if (!is_string($property)) {
            return;
        }

        if (!str_contains($property, 'node://')) {
            return;
        }

        $absolute = true;

        $processedContent = preg_replace_callback(
            LinkingService::PATTERN_SUPPORTED_URIS,
            function (array $matches) use (
                $node,
                $controllerContext,
                &$unresolvedUris,
                $absolute
            ) {
                switch ($matches[1]) {
                    case 'node':
                        $resolvedUri = $this->linkingService->resolveNodeUri(
                            $matches[0],
                            $node,
                            $controllerContext,
                            $absolute
                        );
                        break;
                    default:
                        $resolvedUri = null;
                }

                if ($resolvedUri === null) {
                    $unresolvedUris[] = $matches[0];
                    return $matches[0];
                }

                return $resolvedUri;
            },
            $property
        );
    }

    protected function createResultItem(Context $context, Domain $domain, NodeInterface $node, string $uri): void
    {
        $documentNode = $this->getDocumentNodeOfContentNode($node);
        $sourceNodeIdentifier = $documentNode->getNodeData()->getIdentifier();
        $sourceNodePath = $documentNode->getNodeData()->getPath();

        $resultItem = new ResultItem();
        $resultItem->setDomain($domain->getHostname());
        $resultItem->setSource($sourceNodeIdentifier);
        $resultItem->setSourcePath($sourceNodePath);
        $resultItem->setTarget($uri);
        $resultItem->setStatusCode(404);
        $resultItem->setCreatedAt($context->getCurrentDateTime());
        $resultItem->setCheckedAt($context->getCurrentDateTime());

        $this->resultItemStorage->add($resultItem);
    }

    protected function getDocumentNodeOfContentNode(NodeInterface $node): NodeInterface
    {
        return FlowQuery::q([$node])->closest('[instanceof Neos.Neos:Document]')->get(0);
    }
}

<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Crawler;

use CodeQ\LinkChecker\Domain\Factory\ControllerContextFactory;
use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use GuzzleHttp\Psr7\ServerRequest;
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
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Dto\RouteContext;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\RouterInterface;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Http\Factories\UriFactory;
use Neos\Neos\Domain\Model\Domain;
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
     * @Flow\Inject
     * @var RouterInterface
     */
    protected $router;

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
        $allContentNodes = (new FlowQuery([$context->getCurrentSiteNode()]))->find('[instanceof Neos.Neos:Content]')->get();

        $messages = [];

        foreach ($allContentNodes as $node) {
            /** @var Node $node */
            $nodeData = $node->getNodeData();

            $unresolvedUris = [];
            $invalidPhoneNumbers = [];
            $controllerContext = $this->controllerContextFactory->create($domain);

            $properties = $nodeData->getProperties();

            foreach ($properties as $property) {
                $this->crawlPropertyForNodesAndAssets($property, $node, $controllerContext, $unresolvedUris);
                $this->crawlPropertyForTelephoneNumbers($property, $invalidPhoneNumbers);
            }

            foreach ($unresolvedUris as $uri) {
                $messages[] = 'Not found: ' . $uri;

                $this->createResultItem($context, $domain, $node, $uri, 404);
            }
            foreach ($invalidPhoneNumbers as $phoneNumber) {
                $messages[] = 'Invalid format: ' . $phoneNumber;

                /* @see https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml - 490 is unassigned, and so we can use it */
                $this->createResultItem($context, $domain, $node, $phoneNumber, 490);
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
    protected function crawlPropertyForNodesAndAssets(
        $property,
        NodeInterface $node,
        ControllerContext $controllerContext,
        array &$unresolvedUris
    ): void {
        if (!is_string($property)) {
            return;
        }

        if (!str_contains($property, 'node://') && !str_contains($property, 'asset://')) {
            return;
        }

        $absolute = true;

        preg_replace_callback(
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

                        if ($resolvedUri !== null) {
                            // Check if uri is reachable or if a parent page is disabled for example
                            // Same logic as in RoutingMiddleware
                            $uri = (new UriFactory())->createUri($resolvedUri);
                            $parameters = RouteParameters::createEmpty()
                                ->withParameter('requestUriHost', $uri->getHost());

                            $request = new ServerRequest('GET', $uri);
                            $routeContext = new RouteContext($request, $parameters);
                            try {
                                $this->router->route($routeContext);
                            } catch (NoMatchingRouteException $e) {
                                $resolvedUri = null;
                            }
                        }

                        break;
                    case 'asset':
                        $resolvedUri = $this->linkingService->resolveAssetUri($matches[0]);
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
        Context $context,
        Domain $domain,
        NodeInterface $node,
        string $uri,
        int $statusCode
    ): void {
        $documentNode = $this->getDocumentNodeOfContentNode($node);
        $sourceNodeIdentifier = $documentNode->getNodeData()->getIdentifier();
        $sourceNodePath = $documentNode->getNodeData()->getPath();

        $resultItem = new ResultItem();
        $resultItem->setDomain($domain->getHostname());
        $resultItem->setSource($sourceNodeIdentifier);
        $resultItem->setSourcePath($sourceNodePath);
        $resultItem->setTarget($uri);
        $resultItem->setStatusCode($statusCode);
        $resultItem->setCreatedAt($context->getCurrentDateTime());
        $resultItem->setCheckedAt($context->getCurrentDateTime());

        $this->resultItemStorage->add($resultItem);
    }

    protected function getDocumentNodeOfContentNode(NodeInterface $node): NodeInterface
    {
        return FlowQuery::q([$node])->closest('[instanceof Neos.Neos:Document]')->get(0);
    }
}

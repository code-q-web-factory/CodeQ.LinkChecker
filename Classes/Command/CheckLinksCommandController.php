<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Command;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Storage\NodeDataStorage;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use Neos\ContentRepository\Domain\Factory\NodeFactory;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Neos\Service\LinkingService;
use Psr\Http\Message\ServerRequestFactoryInterface;

/**
 * @Flow\Scope("singleton")
 * @see https://gist.github.com/hhoechtl/9012d455eab52658bbf4
 */
class CheckLinksCommandController extends CommandController
{
    /**
     * @Flow\Inject
     *
     * @var ResultItemStorage
     */
    protected $resultItemStorage;

    /**
     * @Flow\Inject
     * @var NodeDataStorage
     */
    protected $nodeDataStorage;

    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var ServerRequestFactoryInterface
     */
    protected $serverRequestFactory;

    /**
     * @Flow\Inject
     * @var ActionRequestFactory
     */
    protected $actionRequestFactory;

    protected array $settings;

    /**
     * Inject the settings
     *
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Crawl for invalid links
     *
     * This command crawls an url for invalid internal and external links
     *
     * @param string $url The url to start crawling from
     * @param int $concurrency Maximum number of requests to send concurrently
     * @return void
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws NodeConfigurationException
     * @throws UnresolvedDependenciesException
     * @throws IllegalObjectTypeException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     * @see \Neos\Neos\Fusion\ConvertUrisImplementation::evaluate
     */
    public function crawlCommand(string $url = '', int $concurrency = 10): void
    {
        $context = $this->contextFactory->create();

        $linkingService = $this->linkingService;

        $iterableResult = $this->nodeDataStorage->findAll();

        foreach ($iterableResult as $result) {
            /** @var NodeData $nodeData */
            $nodeData = $result[0];

            $unresolvedUris = [];
            $node = $this->nodeFactory->createFromNodeData($nodeData, $context);
            $controllerContext = $this->createControllerContextFromEnvironment('playground-7.codeq.test'); // TODO: load this from settings

            $properties = $nodeData->getProperties();

            foreach ($properties as $property) {
                if (!is_string($property)) {
                    continue;
                }

                if (!str_contains($property, 'node://')) {
                    continue;
                }

                $absolute = true;

                $processedContent = preg_replace_callback(
                    LinkingService::PATTERN_SUPPORTED_URIS,
                    static function (array $matches) use (
                        $node,
                        $linkingService,
                        $controllerContext,
                        &$unresolvedUris,
                        $absolute
                    ) {
                        switch ($matches[1]) {
                            case 'node':
                                $resolvedUri = $linkingService->resolveNodeUri(
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

            foreach ($unresolvedUris as $uri) {
                $this->outputLine('ERROR: ' . $uri);

                $resultItem = new ResultItem();
                $resultItem->setDomain('playground-7.codeq.test'); // TODO: load this from settings
                $resultItem->setSource($nodeData->getPath());
                $resultItem->setTarget($uri);
                $resultItem->setStatusCode(404);
                $resultItem->setCreatedAt(new \DateTime());
                $resultItem->setCheckedAt(new \DateTime());

                $this->resultItemStorage->add($resultItem);
            }
        }
    }

    /**
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws UnresolvedDependenciesException
     * @see https://github.com/neos/flow-development-collection/issues/2084#issuecomment-696567359
     */
    protected function createControllerContextFromEnvironment(string $requestUriHost): ControllerContext
    {
        $_SERVER['FLOW_REWRITEURLS'] = 1;

        $routeParameters = RouteParameters::createEmpty()
            ->withParameter('requestUriHost', $requestUriHost);

        $fakeHttpRequest = $this->serverRequestFactory->createServerRequest('GET', 'http://' . $requestUriHost)
            ->withAttribute(ServerRequestAttributes::ROUTING_PARAMETERS, $routeParameters);

        $fakeActionRequest = $this->actionRequestFactory->createActionRequest($fakeHttpRequest);

        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($fakeActionRequest);

        return new ControllerContext(
            $fakeActionRequest,
            new ActionResponse(),
            new Arguments([]),
            $uriBuilder
        );
    }
}

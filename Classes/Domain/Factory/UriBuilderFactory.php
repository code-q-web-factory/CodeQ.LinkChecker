<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Factory;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Domain\Model\Domain;
use Psr\Http\Message\ServerRequestFactoryInterface;

/**
 * @Flow\Scope("singleton")
 */
class UriBuilderFactory
{
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

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     */
    public function create(Domain $domain): UriBuilder
    {
        if ($this->uriBuilder instanceof UriBuilder) {
            return $this->uriBuilder;
        }

        $_SERVER['FLOW_REWRITEURLS'] = 1;

        $routeParameters = RouteParameters::createEmpty()
            ->withParameter('requestUriHost', $domain->getHostname());

        $fakeHttpRequest = $this->serverRequestFactory
            ->createServerRequest('GET', $domain->getScheme() . '://' . $domain->getHostname())
            ->withAttribute(ServerRequestAttributes::ROUTING_PARAMETERS, $routeParameters);

        $fakeActionRequest = $this->actionRequestFactory->createActionRequest($fakeHttpRequest);

        $this->uriBuilder = new UriBuilder();
        $this->uriBuilder->setRequest($fakeActionRequest);

        return $this->uriBuilder;
    }
}

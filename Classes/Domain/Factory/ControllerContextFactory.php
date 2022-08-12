<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Factory;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Neos\Domain\Model\Domain;

/**
 * @Flow\Scope("singleton")
 */
class ControllerContextFactory
{
    /**
     * @var UriBuilderFactory
     * @Flow\Inject
     */
    protected $uriBuilderFactory;

    /**
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws UnresolvedDependenciesException
     * @see https://github.com/neos/flow-development-collection/issues/2084#issuecomment-696567359
     */
    public function create(Domain $domain): ControllerContext
    {
        $uriBuilder = $this->uriBuilderFactory->create($domain);
        $fakeActionRequest = $uriBuilder->getRequest();

        return new ControllerContext(
            $fakeActionRequest,
            new ActionResponse(),
            new Arguments([]),
            $uriBuilder
        );
    }
}

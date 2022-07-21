<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Controller\Backend;

use CodeQ\LinkChecker\Domain\Crawler\ContentNodeCrawler;
use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Service\DomainService;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Exception\IndexOutOfBoundsException;
use Neos\Flow\I18n\Exception\InvalidFormatPlaceholderException;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Exception\InvalidActionNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\InvalidControllerNameException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\ObjectManagement\Exception\UnresolvedDependenciesException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\Domain;

/**
 * @Flow\Scope("singleton")
 */
class ModuleController extends AbstractModuleController
{
    /**
     * @var FusionView
     */
    protected $view;

    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * @Flow\Inject
     *
     * @var ResultItemStorage
     */
    protected $resultItemStorage;

    /**
     * @Flow\Inject
     * @var DomainService
     */
    protected $domainService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var ContentNodeCrawler
     */
    protected $contentNodeCrawler;

    public function indexAction(): void
    {
        $resultItems = $this->resultItemStorage->findAll();
        $flashMessages = $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush();

        $this->view->assignMultiple([
            'links' => $resultItems,
            'flashMessages' => $flashMessages,
        ]);
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws InvalidQueryException
     * @throws MissingActionNameException
     * @throws StopActionException
     * @throws UnresolvedDependenciesException
     * @throws \Neos\Eel\Exception
     * @throws IndexOutOfBoundsException
     * @throws InvalidFormatPlaceholderException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    public function runAction(): void
    {
        $this->resultItemStorage->truncate();

        /** @var non-empty-string[] $urlsToCrawl */
        $urlsToCrawl = $this->settings['urlsToCrawl'];

        $domainsToCrawl = $this->domainService->getDomainsToCrawl($urlsToCrawl);

        if (count($domainsToCrawl) === 0) {
            $this->addFlashMessage(
                $this->translator->translatebyid('noDomainsFound', [], null, null, 'Modules', 'CodeQ.LinkChecker'),
                '',
                Message::SEVERITY_ERROR,
                [],
                1412373973
            );
            $this->redirect('index');
        }

        foreach ($domainsToCrawl as $domainToCrawl) {
            $messagesPerDomain = $this->crawlDomain($domainToCrawl);

            $this->addFlashMessage(
                $this->translator->translateById(
                    'errorsFound',
                    [
                        'amountOfErrors' => count($messagesPerDomain),
                        'domain' => $domainToCrawl->getHostname(),
                    ],
                    count($messagesPerDomain),
                    null,
                    'Modules',
                    'CodeQ.LinkChecker'
                ),
                '',
                Message::SEVERITY_ERROR,
                [],
                1412373972
            );
        }
        $this->redirect('index');
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws InvalidActionNameException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws InvalidControllerNameException
     * @throws MissingActionNameException
     * @throws UnresolvedDependenciesException
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws \Neos\Neos\Exception
     */
    protected function crawlDomain(Domain $domain): array
    {
        $context = $this->contextFactory->create([
            'currentSite' => $domain->getSite(),
            'currentDomain' => $domain,
        ]);

        return $this->contentNodeCrawler->crawl($context, $domain);
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function deleteAction(ResultItem $resultItem): void
    {
        $this->resultItemStorage->remove($resultItem);

        $this->addFlashMessage(sprintf('%s deleted', $resultItem->getSource()));
        $this->redirect('index');
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function ignoreAction(ResultItem $resultItem): void
    {
        $this->resultItemStorage->ignore($resultItem);

        $this->addFlashMessage(sprintf('%s ignored', $resultItem->getSource()));
        $this->redirect('index');
    }
}

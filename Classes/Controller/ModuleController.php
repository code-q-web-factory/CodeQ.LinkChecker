<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Controller;

use CodeQ\LinkChecker\Domain\Model\ResultItem;
use CodeQ\LinkChecker\Domain\Storage\ResultItemStorage;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;

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
     *
     * @var ResultItemStorage
     */
    protected $resultItemStorage;

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
     * @throws StopActionException
     */
    public function runAction(): void
    {
        $this->addFlashMessage('Hello from run action!');
        $this->redirect('index');
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function markAsDoneAction(ResultItem $resultItem): void
    {
        $this->resultItemStorage->markAsDone($resultItem);

        $this->addFlashMessage(sprintf('%s marked as done', $resultItem->getSource()));
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

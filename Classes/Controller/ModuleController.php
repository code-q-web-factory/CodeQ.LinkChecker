<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Controller;

use Neos\Flow\Annotations as Flow;
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

    public function indexAction(): void
    {
        $links = [
            [
                'uuid' => '332c1ba3-d97b-407c-9e21-32a178d6d2e6',
                'domain' => 'meine-neos-webseite.at',
                'source' => '/unterseite',
                'target' => 'https://invalid-url.com/',
                'error' => 'Not found (404)',
                'foundAt' => new \DateTimeImmutable('07-06-2022 14:35'),
            ],
            [
                'uuid' => '332c1ba3-d97b-407c-9e21-32a178d6d2e7',
                'domain' => 'meine-neos-webseite.at',
                'source' => '/unterseite',
                'target' => 'https://broken-url.com/',
                'error' => 'Internal Server Error (500)',
                'foundAt' => new \DateTimeImmutable('08-06-2022 15:11'),
            ],
        ];

        $flashMessages = $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush();

        $this->view->assignMultiple([
            'links' => $links,
            'flashMessages' => $flashMessages,
        ]);
    }

    public function runAction(): void
    {
        $this->addFlashMessage('Hello from run action!');
        $this->redirect('index');
    }

    public function markAsDoneAction(): void
    {
        $this->addFlashMessage('Hello from markAsDone action!');
        $this->redirect('index');
    }

    public function ignoreAction(): void
    {
        $this->addFlashMessage('Hello from ignore action!');
        $this->redirect('index');
    }
}

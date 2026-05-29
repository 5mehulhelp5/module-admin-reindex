<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\Adminhtml\License;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Success extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdminReindex::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->prepend('License Activated — Admin Reindex');
        return $page;
    }
}

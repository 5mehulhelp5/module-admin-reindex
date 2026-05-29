<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\Adminhtml\License;

use ETechFlow\AdminReindex\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdminReindex::config';

    public function __construct(
        Context $context,
        private readonly LicenseValidator $licenseValidator,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            $this->messageManager->addSuccessMessage(
                (string) __('Your Admin Reindex license is active. Configure the module below.')
            );
            return $this->resultRedirectFactory->create()->setPath('indexer/indexer/list');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((string) __('Admin Reindex — License Required'));
        return $resultPage;
    }
}

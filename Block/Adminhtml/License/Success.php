<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Block\Adminhtml\License;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Success extends Template
{
    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getIssuedKey(): string
    {
        return trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/license/issued_key'));
    }

    public function getIssuedDomain(): string
    {
        return trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/license/issued_domain'));
    }

    public function getConfigUrl(): string
    {
        return (string) $this->getUrl(
            'adminhtml/system_config/edit',
            ['section' => 'etechflow_adminreindex']
        );
    }

    public function getIndexManagementUrl(): string
    {
        return (string) $this->getUrl('indexer/indexer/list');
    }
}

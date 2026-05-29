<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Block\Adminhtml\License;

use ETechFlow\AdminReindex\Model\LicenseValidator;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Gate extends Template
{
    private const PORTAL_URL_PATH = 'etechflow_adminreindex/license/portal_url';

    public function __construct(
        Context $context,
        private readonly LicenseValidator $licenseValidator,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormKey(): string
    {
        if ($this->formKey !== null) {
            return $this->formKey->getFormKey();
        }
        return \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Data\Form\FormKey::class)
            ->getFormKey();
    }

    public function getConfigUrl(): string
    {
        return (string) $this->getUrl(
            'adminhtml/system_config/edit',
            ['section' => 'etechflow_adminreindex', '_fragment' => 'etechflow_adminreindex_license-head']
        );
    }

    public function getPortalBrowserUrl(): string
    {
        $v = (string) $this->_scopeConfig->getValue(self::PORTAL_URL_PATH);
        return rtrim($v, '/');
    }

    public function getCurrentDomain(): string
    {
        return $this->licenseValidator->getCurrentHost();
    }

    public function getConfiguredKey(): string
    {
        return $this->licenseValidator->getConfiguredKey();
    }
}

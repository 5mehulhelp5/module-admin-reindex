<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Admin-config wrapper for ETechFlow_AdminReindex. Narrow on purpose:
 * the module only needs a master kill-switch and the license check.
 */
class Config
{
    private const XML_PATH_ENABLED = 'etechflow_adminreindex/general/enabled';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param LicenseValidator     $licenseValidator
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    /**
     * Master kill-switch. Returns false when the license is invalid for
     * the current Magento host, regardless of the admin enable flag.
     *
     * License check fires first so an unlicensed install silently
     * hides the Reindex buttons without crashing the admin grid.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        if (!$this->licenseValidator->isValid()) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

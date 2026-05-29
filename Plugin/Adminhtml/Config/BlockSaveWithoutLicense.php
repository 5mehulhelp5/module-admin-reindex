<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Plugin\Adminhtml\Config;

use ETechFlow\AdminReindex\Model\LicenseValidator;
use Magento\Config\Model\Config;
use Magento\Framework\Exception\LocalizedException;

class BlockSaveWithoutLicense
{
    public function __construct(
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    public function beforeSave(Config $subject): void
    {
        if ($subject->getSection() !== 'etechflow_adminreindex') {
            return;
        }

        // Always allow saving license keys, portal URLs, and Stripe credentials.
        // Only block if the user is explicitly trying to enable the module without a license.
        $groups = $subject->getData('groups') ?? [];
        $enabledValue = $groups['general']['fields']['enabled']['value'] ?? null;

        if ($enabledValue === null) {
            return;
        }

        if ((string) $enabledValue === '0') {
            return;
        }

        if (!$this->licenseValidator->isValid()) {
            throw new LocalizedException(
                __('A valid Admin Reindex license is required to enable the module. Please activate your license first.')
            );
        }
    }
}

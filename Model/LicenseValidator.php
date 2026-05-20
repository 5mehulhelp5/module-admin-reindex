<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Validates the per-domain license key for ETechFlow_AdminReindex.
 *
 * Customers receive a license key bound to their Magento base URL host.
 * The module gates its behaviour on this validation: an invalid key
 * causes the module to silently no-op (the admin Reindex buttons
 * vanish) so the install never breaks if a key expires or is missing.
 *
 * Same pattern as every other eTechFlow module:
 *   - Per-module key activates this module only.
 *   - Bundle key (shared HMAC secret across modules) activates ALL
 *     eTechFlow modules at once.
 *   - "Production Environment = No" bypasses licensing for dev/staging.
 *   - Common dev hostnames (.test, .local, IPs, *.magento.cloud, etc.)
 *     are detected automatically and bypass licensing too.
 */
class LicenseValidator
{
    public const XML_PATH_LICENSE_KEY            = 'etechflow_adminreindex/license/license_key';
    public const XML_PATH_PRODUCTION_ENVIRONMENT = 'etechflow_adminreindex/license/production_environment';

    /** Shared config path — same value across all eTechFlow modules. */
    public const XML_PATH_BUNDLE_LICENSE_KEY = 'etechflow_bundle/license/license_key';

    private const MODULE_ID = 'admin-reindex';

    /** Shared bundle identifier — must match across all eTechFlow modules. */
    private const BUNDLE_ID = 'etechflow-bundle';

    /** Per-module HMAC secret. Split across constants to make casual extraction harder. */
    private const SECRET_FRAGMENTS = [
        'eTF-AR-2026',
        'r4M9-tQ8w',
        'P2bN-jK6h',
        'F7sV-cZ3x',
    ];

    /** Shared bundle HMAC secret. MUST be identical in every eTechFlow module's LicenseValidator. */
    private const BUNDLE_SECRET_FRAGMENTS = [
        'eTF-BUNDLE-2026',
        'k2D9-mP4x',
        'L8nR-vH2j',
        'X7tY-zW5q',
    ];

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface  $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Whether the module is licensed for the current Magento install.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $host = $this->getCurrentHost();
        if ($host === '') {
            return false;
        }

        if (!$this->isProductionEnvironment()) {
            return true;
        }

        if ($this->isDevelopmentHost($host)) {
            return true;
        }

        $configuredKey = $this->getConfiguredKey();
        if ($configuredKey !== '' && hash_equals($this->computeKey($host), $configuredKey)) {
            return true;
        }

        $bundleKey = $this->getConfiguredBundleKey();
        if ($bundleKey !== '' && hash_equals($this->computeBundleKey($host), $bundleKey)) {
            return true;
        }

        return false;
    }

    /**
     * Compute the per-module license key for an arbitrary host.
     *
     * @param string $host
     * @return string
     */
    public function computeKey(string $host): string
    {
        $payload = $this->canonicalize($host) . ':' . self::MODULE_ID;
        $secret  = implode('', self::SECRET_FRAGMENTS);
        $raw     = hash_hmac('sha256', $payload, $secret, true);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Compute the bundle license key for an arbitrary host.
     *
     * @param string $host
     * @return string
     */
    public function computeBundleKey(string $host): string
    {
        $payload = $this->canonicalize($host) . ':' . self::BUNDLE_ID;
        $secret  = implode('', self::BUNDLE_SECRET_FRAGMENTS);
        $raw     = hash_hmac('sha256', $payload, $secret, true);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Canonicalize a host: lowercase, strip whitespace, drop a leading www.
     *
     * @param string $host
     * @return string
     */
    private function canonicalize(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }

    /**
     * @return string
     */
    public function getConfiguredKey(): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        );
        return trim((string) $value);
    }

    /**
     * @return string
     */
    public function getConfiguredBundleKey(): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_BUNDLE_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        );
        return trim((string) $value);
    }

    /**
     * @return bool
     */
    public function isProductionEnvironment(): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PRODUCTION_ENVIRONMENT,
            ScopeInterface::SCOPE_STORE
        );
        if ($value === null || $value === '') {
            return true;
        }
        return (bool) $value;
    }

    /**
     * @return string
     */
    public function getCurrentHost(): string
    {
        try {
            $url = $this->storeManager->getStore()->getBaseUrl();
            $host = parse_url($url, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Public wrapper for the dev-host detector — admin status block uses it.
     *
     * @param string|null $host
     * @return bool
     */
    public function isDevHost(?string $host = null): bool
    {
        $check = $host !== null
            ? $this->canonicalize($host)
            : $this->canonicalize($this->getCurrentHost());
        return $this->isDevelopmentHost($check);
    }

    /**
     * Identify development hosts that bypass licensing. Mirrors the
     * standard Amasty/Aheadworks/MageWorx pattern.
     *
     * @param string $host
     * @return bool
     */
    private function isDevelopmentHost(string $host): bool
    {
        if ($host === 'localhost' || str_starts_with($host, '127.')) {
            return true;
        }
        if (str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) {
            return true;
        }
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) {
            return true;
        }

        $devSuffixes = ['.test', '.local', '.localhost', '.dev', '.example', '.invalid'];
        foreach ($devSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $devPrefixes = ['staging.', 'stage.', 'dev.', 'qa.', 'uat.', 'test.', 'preview.', 'sandbox.'];
        foreach ($devPrefixes as $prefix) {
            if (str_starts_with($host, $prefix)) {
                return true;
            }
        }

        if (preg_match('/-(staging|stage|dev|qa|uat|test|preview|sandbox)\./', $host)) {
            return true;
        }

        $cloudSuffixes = ['.magento.cloud', '.magentocloud.com', '.cloud.magento'];
        foreach ($cloudSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $tunnelSuffixes = ['.ngrok.io', '.ngrok-free.app', '.loca.lt', '.serveo.net'];
        foreach ($tunnelSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}

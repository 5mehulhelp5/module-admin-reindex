<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\License;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Revoke implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly CacheInterface $cache,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute(): ResultInterface
    {
        $result      = $this->jsonFactory->create();
        $body        = json_decode((string) $this->request->getContent(), true) ?? [];
        $providedKey = trim((string) ($body['license_key'] ?? ''));

        if ($providedKey === '' || !preg_match('/^SP-[A-F0-9\-]+$/i', $providedKey)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid key format']);
        }

        $storedKey = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/license/issued_key'));
        if ($storedKey === '' || !hash_equals(strtoupper($storedKey), strtoupper($providedKey))) {
            return $result->setHttpResponseCode(403)->setData(['error' => 'Key mismatch']);
        }

        foreach ([
            'etechflow_adminreindex/license/license_key',
            'etechflow_adminreindex/license/issued_key',
            'etechflow_adminreindex/license/issued_domain',
            'etechflow_adminreindex/license/stripe_session',
            'etechflow_adminreindex/license/stripe_subscription',
            'etechflow_adminreindex/license/stripe_customer',
        ] as $path) {
            $this->configWriter->save($path, '');
        }

        $this->configWriter->save('etechflow_adminreindex/license/revoked',   '1');
        $this->configWriter->save('etechflow_adminreindex/license/issued_at', '0');
        $this->cache->remove('etf_ar_lic_' . md5($storedKey));
        $this->cacheTypeList->cleanType('config');

        return $result->setData(['success' => true]);
    }
}

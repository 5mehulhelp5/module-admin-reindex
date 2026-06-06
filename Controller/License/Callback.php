<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\License;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\View\Result\PageFactory;
use ETechFlow\AdminReindex\Model\LicenseValidator;

class Callback implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly CacheInterface $cache,
        private readonly Curl $curl,
        private readonly EncryptorInterface $encryptor,
        private readonly RedirectFactory $redirectFactory,
        private readonly PageFactory $pageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $sessionId = trim((string) $this->request->getParam('session_id', ''));

        if ($sessionId === '' || !preg_match('/^cs_[a-zA-Z0-9_]+$/', $sessionId)) {
            return $this->fail('Invalid or missing session_id.');
        }

        $raw       = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/payment/stripe_secret_key'));
        $stripeKey = preg_match('/^\d+:\d+:/', $raw) ? trim($this->encryptor->decrypt($raw)) : $raw;
        if ($stripeKey === '') {
            return $this->fail('Payment not configured.');
        }

        $session = $this->fetchSession($stripeKey, $sessionId);
        if (empty($session['id'])) {
            return $this->fail('Could not verify payment session with Stripe.');
        }

        $payStatus = $session['payment_status'] ?? '';
        $status    = $session['status']         ?? '';
        if ($status !== 'complete' && $payStatus !== 'paid') {
            return $this->fail('Payment not completed.');
        }

        $module = (string) ($session['metadata']['module'] ?? '');
        if ($module !== '' && $module !== 'admin-reindex') {
            return $this->fail('Session belongs to a different module: ' . $module);
        }

        $domain    = (string) ($session['metadata']['domain']           ?? '');
        $plan      = (string) ($session['metadata']['plan']             ?? 'unknown');
        $returnUrl = (string) ($session['metadata']['admin_return_url'] ?? '');
        $name      = (string) ($session['metadata']['customer_name']    ?? '');
        $email     = (string) ($session['customer_email']               ?? '');
        $subId     = (string) ($session['subscription']                 ?? '');
        $custId    = (string) ($session['customer']                     ?? '');
        $amount    = (int)    ($session['amount_total']                 ?? 0);

        $key = $this->generateKey();

        $this->configWriter->save('etechflow_adminreindex/license/license_key',    $key);
        $this->configWriter->save('etechflow_adminreindex/license/issued_key',     $key);
        $this->configWriter->save('etechflow_adminreindex/license/issued_at',      (string) time());
        $this->configWriter->save('etechflow_adminreindex/license/revoked',        '0');
        $this->configWriter->save('etechflow_adminreindex/license/issued_domain',  $domain);
        $this->configWriter->save('etechflow_adminreindex/license/stripe_session', $sessionId);
        if ($subId  !== '') {
            $this->configWriter->save('etechflow_adminreindex/license/stripe_subscription', $subId);
        }
        if ($custId !== '') {
            $this->configWriter->save('etechflow_adminreindex/license/stripe_customer', $custId);
        }

        $this->cacheTypeList->cleanType('config');
        $this->cache->remove('etf_ar_lic_' . md5($domain . ':' . $key));

        $this->notifyPortal($sessionId, $domain, $name, $email, $plan, $key, $amount);

        if ($returnUrl !== '') {
            $redirect = $this->redirectFactory->create();
            $redirect->setUrl($returnUrl . '?payment_done=1&plan=' . urlencode($plan));
            return $redirect;
        }

        $redirect = $this->redirectFactory->create();
        $redirect->setPath('etechflow_admin_reindex/license/success');
        return $redirect;
    }

    private function notifyPortal(
        string $sessionId, string $domain, string $name,
        string $email, string $plan, string $licenseKey, int $amount
    ): void {
        try {
            $apiUrl = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/license/portal_api_url'));
            if ($apiUrl === '') {
                $apiUrl = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/license/portal_url'));
            }
            if ($apiUrl === '' || str_contains($apiUrl, '127.0.0.1') || str_contains($apiUrl, 'localhost')) {
                return;
            }
            $payload = json_encode([
                'session_id' => $sessionId, 'domain' => $domain, 'name' => $name,
                'email' => $email, 'plan' => $plan, 'module' => 'admin-reindex',
                'license_key' => $licenseKey, 'amount' => $amount,
            ]);
            $ch = curl_init(rtrim($apiUrl, '/') . '/api/register-subscription');
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable) {
        }
    }

    private function fetchSession(string $key, string $id): array
    {
        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->addHeader('Authorization', 'Bearer ' . $key);
        $this->curl->get('https://api.stripe.com/v1/checkout/sessions/' . urlencode($id));
        $data = json_decode($this->curl->getBody(), true);
        return is_array($data) ? $data : [];
    }

    private function generateKey(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(10)));
        return 'SP-' . implode('-', str_split($hex, 4));
    }

    private function fail(string $msg): ResultInterface
    {
        $redirect = $this->redirectFactory->create();
        $redirect->setUrl('/');
        return $redirect;
    }
}
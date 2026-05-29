<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Controller\Adminhtml\License;

use ETechFlow\AdminReindex\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

class CreateSession extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdminReindex::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Curl $curl,
        private readonly LicenseValidator $licenseValidator,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        ob_start();
        $result = $this->jsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                ob_get_clean();
                return $result->setData(['error' => 'Invalid request method']);
            }

            $plan  = trim((string) $this->getRequest()->getParam('plan',  ''));
            $email = trim((string) $this->getRequest()->getParam('email', ''));
            $name  = trim((string) $this->getRequest()->getParam('name',  ''));

            if ($plan === '' || $email === '' || $name === '') {
                ob_get_clean();
                return $result->setData(['error' => 'All fields are required']);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ob_get_clean();
                return $result->setData(['error' => 'Please enter a valid email address']);
            }

            $stripeKey = $this->getStripeSecretKey();
            if ($stripeKey === '') {
                ob_get_clean();
                return $result->setData(['error' => 'Stripe is not configured. Go to Stores > Configuration > eTechFlow > Admin Reindex > Payment (Stripe) and add your Stripe Secret Key.']);
            }

            $domain   = $this->licenseValidator->getCurrentHost();
            $planInfo = $this->getPlanInfo($plan);
            if ($planInfo === null) {
                ob_get_clean();
                return $result->setData(['error' => 'Unknown plan: ' . $plan]);
            }

            $frontendBase   = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
            $successUrl     = $frontendBase . '/etechflow-ar/license/callback?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl      = (string) $this->getUrl('etechflow_admin_reindex/license/index');
            $adminReturnUrl = (string) $this->getUrl('etechflow_admin_reindex/license/success');

            $session = $this->callStripe(
                $stripeKey, $planInfo, $email, $name,
                $domain, $plan, $successUrl, $cancelUrl, $adminReturnUrl
            );

            if (isset($session['error'])) {
                ob_get_clean();
                return $result->setData(['error' => $session['error']['message'] ?? 'Stripe error']);
            }

            $url = $session['url'] ?? '';
            if ($url === '') {
                ob_get_clean();
                return $result->setData(['error' => 'No checkout URL from Stripe. Response: ' . json_encode(array_keys($session))]);
            }

            ob_get_clean();
            return $result->setData(['url' => $url]);

        } catch (\Throwable $e) {
            ob_get_clean();
            return $result->setData(['error' => get_class($e) . ': ' . $e->getMessage()]);
        }
    }

    private function getStripeSecretKey(): string
    {
        $raw = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/payment/stripe_secret_key'));
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d+:\d+:/', $raw)) {
            $enc = $this->_objectManager->get(\Magento\Framework\Encryption\EncryptorInterface::class);
            return trim($enc->decrypt($raw));
        }
        return $raw;
    }

    private function getPlanInfo(string $plan): ?array
    {
        $priceId = trim((string) $this->scopeConfig->getValue('etechflow_adminreindex/payment/stripe_price_' . str_replace('-', '_', $plan)));
        if ($priceId !== '') {
            return ['price_id' => $priceId, 'mode' => 'subscription', 'label' => ucfirst($plan) . ' Plan'];
        }
        $catalog = [
            'solo'         => ['amount' => 1900,  'currency' => 'gbp', 'label' => 'Solo Plan',         'mode' => 'payment'],
            'growth'       => ['amount' => 3900,  'currency' => 'gbp', 'label' => 'Growth Plan',       'mode' => 'payment'],
            'business'     => ['amount' => 6900,  'currency' => 'gbp', 'label' => 'Business Plan',     'mode' => 'payment'],
            'all_channels' => ['amount' => 9900,  'currency' => 'gbp', 'label' => 'All-Channels Plan', 'mode' => 'payment'],
        ];
        return $catalog[$plan] ?? null;
    }

    private function callStripe(
        string $key, array $info, string $email, string $name,
        string $domain, string $plan, string $successUrl, string $cancelUrl, string $returnUrl
    ): array {
        $p = [
            'mode'                       => $info['mode'],
            'customer_email'             => $email,
            'success_url'                => $successUrl,
            'cancel_url'                 => $cancelUrl,
            'metadata[domain]'           => $domain,
            'metadata[plan]'             => $plan,
            'metadata[module]'           => 'admin-reindex',
            'metadata[customer_name]'    => $name,
            'metadata[admin_return_url]' => $returnUrl,
        ];
        if (isset($info['price_id'])) {
            $p['line_items[0][price]']    = $info['price_id'];
            $p['line_items[0][quantity]'] = '1';
        } else {
            $p['line_items[0][price_data][currency]']           = $info['currency'];
            $p['line_items[0][price_data][unit_amount]']        = (string) $info['amount'];
            $p['line_items[0][price_data][product_data][name]'] = 'Admin Reindex - ' . $info['label'];
            $p['line_items[0][quantity]']                       = '1';
        }

        $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->addHeader('Authorization', 'Bearer ' . $key);
        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->post('https://api.stripe.com/v1/checkout/sessions', http_build_query($p));

        $body   = $this->curl->getBody();
        $status = $this->curl->getStatus();
        $data   = json_decode($body, true);
        return is_array($data) ? $data : ['error' => ['message' => 'Stripe HTTP ' . $status . ': ' . substr((string) $body, 0, 200)]];
    }
}
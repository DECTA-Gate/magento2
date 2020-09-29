<?php
namespace Decta\Decta\Model;

use Exception;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;

class DectaApi
{
    const DECTA_MODULE_VERSION = 'v1.0.1.0';
    const ROOT_URL = 'https://gate.decta.com';
    const LOG_URL = '/var/log/decta.log';

    public function __construct(Curl $curl, Logger $logger, ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
        $this->private_key =$this->scopeConfig->getValue('payment/decta_decta/private_key');
        $this->public_key = $this->scopeConfig->getValue('payment/decta_decta/public_key');
        $this->logger = $logger;
        $this->logger->addWriter(new Stream(BP . self::LOG_URL));
        $this->curl = $curl;
    }

    public function createPayment($order, $successUrl, $failureUrl)
    {
        $params = [
        'number' => (string)$order->getEntityId(),
        'referrer' => 'Magento v2.x module ' . self::DECTA_MODULE_VERSION,
        'language' =>  'en',
        'success_redirect' => $successUrl,
        'failure_redirect' => $failureUrl,
        'currency' => $order->getOrderCurrencyCode()
        ];

        $this->addUserData($order, $params);
        $this->addProducts($order, $params);

        $payment = $this->createOrder($params);

        return $payment;
    }

    protected function addUserData($order, &$params)
    {
        $billingAddress = $order->getBillingAddress();
        $telephone = $billingAddress->getTelephone();

        $user_data = [
        'email' => $order->getCustomerEmail(),
        'first_name' => $order->getCustomerFirstname(),
        'last_name' => $order->getCustomerLastname(),
        'phone' => $telephone,
        'send_to_email' => true,
        ];

        $findUser = $this->getUser($user_data['email'], $user_data['phone']);

        if (!$findUser) {
            if ($this->createUser($user_data)) {
                $findUser = $this->getUser($user_data['email'], $user_data['phone']);
            }
        }

        $user_data['original_client'] = $findUser['id'];

        $params['client'] = $user_data;
    }

    protected function addProducts($order, &$params)
    {
        $params['products'][] = [
            'price' => round($order->getGrandTotal(), 2),
            'title' => 'default',
            'quantity' => 1
        ];
    }

    public function createOrder($params)
    {
        $this->logger->info(sprintf("Loading payment form for order #%s", $params['number']));
        $result = $this->call('POST', '/api/v0.6/orders/', $params);

        if ($result == null) {
            return null;
        }

        if (isset($result['full_page_checkout']) && isset($result['id'])) {
            $this->logger->info(sprintf("Form loaded successfully for order #%s", $params['number']));
            return $result;
        } else {
            return null;
        }
    }

    public function getUser($filter_email, $filter_phone)
    {
        $params['filter_email'] = $filter_email;
        $params['filter_phone'] = $filter_phone;
        $users = $this->call('GET', '/api/v0.6/clients/', $params);

        $this->logger->info(var_export($users, true));

        return isset($users['results'][0]) ? $users['results'][0] : null;
    }

    public function createUser($params)
    {
        return $this->call('POST', '/api/v0.6/clients/', $params);
    }

    public function wasPaymentSuccessful($order_id, $payment_id)
    {
        $this->logger->info(sprintf("Validating payment for order #%s, payment #%s", $order_id, $payment_id));

        $order_id = (string)$order_id;
        $result = $this->call('GET', sprintf('/api/v0.6/orders/%s/', $payment_id));

        if ($result == null) {
            return false;
        }

        $payment_has_matching_order_id = $order_id == (string)$result['number'];

        if (!$payment_has_matching_order_id) {
            $this->logger->info('Payment object has a wrong order id');
        }

        if ($result && $payment_has_matching_order_id && ($result['status'] == 'paid' ||
        $result['status'] == 'withdrawn')) {
            $this->logger->info(sprintf("Validated order #%s, payment #%s", $order_id, $payment_id));
            return true;
        } else {
            $this->logger->info('Could not validate payment');

            return false;
        }
    }

    public function call($method, $route, $params = [])
    {
        try {
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Authorization', 'Bearer ' . $this->private_key);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_SSL_VERIFYPEER => false
            ];
            $this->curl->setOptions($options);
            $url = self::ROOT_URL . $route;

            switch ($method) {
                case 'POST':
                    $this->logger->info('Post Request:');
                    $this->logger->info($url);
                    $this->logger->info(json_encode($params));
                    $this->curl->post($url, json_encode($params));
                    break;
                case 'GET':
                    $getParams = null;
                    foreach ($params as $key => $value) {
                        $getParams .=  $key . '=' . urlencode($value) . '&';
                    }
                    $getParams = trim($getParams, '&');
                    $request = $getParams ? $url . '?' . $getParams : $url;
                    $this->logger->info('Get Request:');
                    $this->logger->info($request);
                    $this->curl->get($request);
                    break;
            }

            $response = json_decode($this->curl->getBody(), true);
            $this->logger->info('Response:');
            $this->logger->info(var_export($response, true));

            if (!$response) {
                $this->log_error('JSON parsing error/NULL API response');

                return null;
            }

            if (!empty($response['errors'])) {
                $this->log_error('API Errors', var_export($response['errors'], true));

                return null;
            }

            return $response;

        } catch (Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
}

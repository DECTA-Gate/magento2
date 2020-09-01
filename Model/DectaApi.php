<?php
namespace Decta\Decta\Model;

class DectaApi
{
  const DECTA_MODULE_VERSION = 'v3.0';
  const ROOT_URL = 'https://gate-decta.andersenlab.com';

  public function __construct($private_key, $public_key)
  {
    $this->private_key = $private_key;
    $this->public_key = $public_key;
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/decta.log');
    $this->logger = new \Zend\Log\Logger();
    $this->logger->addWriter($writer);
    $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
  }

  public function createPayment($order, $successUrl, $failureUrl)
  {
    $params = array(
      'number' => (string)$order->getEntityId(),
      'referrer' => 'Magento v2.x module ' . self::DECTA_MODULE_VERSION,
      'language' =>  'en',
      'success_redirect' => $successUrl,
      'failure_redirect' => $failureUrl,
      'currency' => $order->getOrderCurrencyCode()
    );

    $this->addUserData($order, $params);
    $this->addProducts($order, $params);
    $this->addCarrier($order, $params);
    $this->logger->info(print_r($params, true));

    $payment = $this->create_payment($params);

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

    $this->logger->info(print_r($user_data, true));

    $findUser = $this->getUser($user_data['email'], $user_data['phone']);

    if(!$findUser) {
      if($this->createUser($user_data)) {
        $findUser = $this->getUser($user_data['email'],$user_data['phone']);
      }
    }

    $user_data['original_client'] = $findUser['id'];

    $params['client'] = $user_data;
  }

  protected function addProducts($order, &$params) {
    $params['products'] = [];
    $orderProducts = $order->getAllVisibleItems();

    foreach ($orderProducts as $orderProduct) {
      $data = $orderProduct->getData();
      $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($data['product_id']);
      $initialPrice = $product['price'];
      $finalPrice = $orderProduct->getPrice();
      $discountAmount = $initialPrice - $finalPrice;

      $price = ($discountAmount > 0) ? $initialPrice : $finalPrice;

      $fields = array(
        'price' => round($price, 2),
        'title' => $orderProduct->getName(),
        'quantity' => (int) $orderProduct->getQtyOrdered(),
      );

      if($orderProduct->getTaxPercent() > 0) {
        $fields['tax_percent'] = round($orderProduct->getTaxPercent(), 2);
      }

      if($discountAmount > 0) {
        $fields['discount_amount'] = round($discountAmount * $orderProduct->getQtyOrdered(), 2);
      }

      $params['products'][] = $fields;
    }
  }

  protected function addCarrier($order, &$params)
  {
    $shippingAmmount = round($order->getShippingAmount(), 2);

    if($shippingAmmount > 0) {
      $params['products'][] = [
        'price' => $shippingAmmount,
        'title' => $order->getShippingDescription(),
        'quantity' => 1,
      ];
    }
  }

  public function create_payment($params)
  {
    $this->logger->info(sprintf("Loading payment form for order #%s", $params['number']));
    $result = $this->call('POST', '/api/v0.6/orders/', $params);

    if ($result == null) {
      return null;
    }


    if (isset($result['full_page_checkout']) && isset($result['id'])) {
      $this->logger->info(sprintf("Form loaded successfully for order #%s", $params['number']));
      $this->logger->info(print_r($result, true));
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

    $this->logger->info(print_r($users, true));

    return isset($users['results'][0]) ? $users['results'][0] : null;
  }

  public function createUser($params)
  {
    return $this->call('POST', '/api/v0.6/clients/', $params);
  }

  public function was_payment_successful($order_id, $payment_id)
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

  public function call($method, $route, $params = array())
  {
    $original_params = $params;

    if (!empty($params)) {
      $params = json_encode($params);
    }

    $authorization_header = "Bearer " . $this->private_key;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, self::ROOT_URL . $route);

    if ($method == 'POST') {
      curl_setopt($ch, CURLOPT_POST, 1);
    }

    if ($method == 'PUT') {
      curl_setopt($ch, CURLOPT_PUT, 1);
    }

    if ($method == 'PUT' or $method == 'POST') {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }

    if($method == 'GET') {
      $get_params = '';

      foreach($original_params as $key=>$value) {
        $get_params .= $key.'='.urlencode($value).'&';
      }

      $get_params = trim($get_params,'&');
      curl_setopt($ch, CURLOPT_URL, self::ROOT_URL.$route.'?'.$get_params);
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-type: application/json',
      'Authorization: ' . $authorization_header
    ));


    $response = curl_exec($ch);

    $this->logger->info('CURL Resquest: ' . print_r(curl_getinfo($ch), true));
    $this->logger->info('authorization_header: ' . $authorization_header);
    $this->logger->info('CURL Response: ' . print_r($response, true));

    if (!$response) {
      $this->logger->info('CURL error: ' . curl_error($ch));
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if (!$result) {
      $this->logger->info('JSON parsing error/null API response');

      return null;
    }

    if ($code >= 400 && $code < 500) {
      $this->logger->info('API Errors', print_r($result, true));

      return null;
    }

    return $result;
  }
}
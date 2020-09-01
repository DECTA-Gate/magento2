<?php
namespace Decta\Decta\Controller\Request;

use Magento\Framework\App\Action\Context;
use Decta\Decta\Model\DectaApi;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Redirect extends \Magento\Framework\App\Action\Action
{
  public function __construct(
    Context $context,
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Customer\Model\Session $customerSession,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Magento\Framework\Locale\Resolver $store,
    \Magento\Framework\UrlInterface $urlBuilder,
    \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
    \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
    \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
    \Magento\Sales\Api\OrderManagementInterface $orderManagement,
    \Magento\Framework\Message\ManagerInterface $messageManager,
    OrderRepositoryInterface $orderRepository
  ) {
    $this->scopeConfig = $scopeConfig;
    $this->checkoutSession = $checkoutSession;
    $this->customerSession = $customerSession;
    $this->store = $store;
    $this->urlBuilder = $urlBuilder;
    $this->resultJsonFactory = $resultJsonFactory;

    $this->customerRepository = $customerRepository;
    $this->addressRepository = $addressRepository;
    $this->orderManagement = $orderManagement;
    $this->messageManager = $messageManager;
    $this->orderRepository = $orderRepository;
    $this->customerSession = $customerSession;

    parent::__construct($context);
  }

  public function execute()
  {
    try {
      $privateKey = $this->scopeConfig->getValue('payment/decta_decta/private_key');
      $publicKey = $this->scopeConfig->getValue('payment/decta_decta/public_key');

      $dectaApi = new DectaApi($privateKey, $publicKey);
      $order = $this->checkoutSession->getLastRealOrder();

      $successUrl = $this->urlBuilder->getUrl('decta/response/success');
      $failureUrl = $this->urlBuilder->getUrl('decta/response/failure');

      $payment = $dectaApi->createPayment($order, $successUrl, $failureUrl);
      $result = $this->resultJsonFactory->create();
      $orderId = $order->getEntityId();
      $orderEntity = $this->orderRepository->get($orderId);

      if (!$payment) {
        $orderEntity->setState(Order::STATE_CANCELED);
        $orderEntity->setStatus(Order::STATE_CANCELED);

        try {
          $this->orderRepository->save($orderEntity);
        } catch (\Exception $e) {
          $dectaApi->logger->info($e);
          $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }

        $dectaApi->logger->info('Order ' . $order->getEntityId() . ' canceled: Could not load decta payment form');
        return $result->setData(['url' => $this->urlBuilder->getUrl('checkout/onepage/failure')]);
      }

      $orderEntity->setState(Order::STATE_PROCESSING);
      $orderEntity->setStatus(Order::STATE_PROCESSING);

      try {
        $this->orderRepository->save($orderEntity);
      } catch (\Exception $e) {
        $dectaApi->logger->info($e);
        $this->messageManager->addExceptionMessage($e, $e->getMessage());
      }

      $this->customerSession->setDectaPaymentId($payment['id']);
      $dectaApi->logger->info('Redirect on full page checkout: ' . $payment['full_page_checkout']);

      return $result->setData(['url' => $payment['full_page_checkout']]);
    } catch (\Exception $e) {
      $dectaApi->logger->info($e->getMessage());
    }
  }
}

<?php
namespace Decta\Decta\Controller\Response;

use Decta\Decta\Model\DectaApi;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Failure extends \Magento\Framework\App\Action\Action
{
  /**
   * @var \Magento\Framework\Controller\Result\JsonFactory
   */
  protected $resultRedirectFactory;
  /**
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
   */
  public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Framework\Controller\ResultFactory $resultRedirectFactory,
    \Magento\Sales\Api\OrderManagementInterface $orderManagement,
    \Magento\Checkout\Model\Session $checkoutSession,
    OrderRepositoryInterface $orderRepository
  )
  {
    $this->resultRedirectFactory = $resultRedirectFactory;
    $this->checkoutSession = $checkoutSession;
    $this->orderManagement = $orderManagement;
    $this->orderRepository = $orderRepository;
    $this->scopeConfig = $scopeConfig;

    parent::__construct($context);
  }
  /**
   * View  page action
   *
   * @return \Magento\Framework\Controller\ResultInterface
   */
  public function execute()
  {

    $privateKey = $this->scopeConfig->getValue('payment/decta_decta/private_key');
    $publicKey = $this->scopeConfig->getValue('payment/decta_decta/public_key');

    $dectaApi = new DectaApi($privateKey, $publicKey);
    $order = $this->checkoutSession->getLastRealOrder();
    $orderId = $order->getEntityId();
    $orderEntity = $this->orderRepository->get($orderId);
    $orderEntity->setState(Order::STATE_CANCELED);
    $orderEntity->setStatus(Order::STATE_CANCELED);

    try {
      $this->orderRepository->save($orderEntity);
    } catch (\Exception $e) {
      $dectaApi->logger->info($e);
      $this->messageManager->addExceptionMessage($e, $e->getMessage());
    }
    $dectaApi->logger->info('Failure callback');
    $resultRedirect = $this->resultRedirectFactory->create();
    $resultRedirect->setPath('checkout/onepage/failure');

    return $resultRedirect;
  }
}
<?php
namespace Decta\Decta\Controller\Response;

use Decta\Decta\Model\DectaApi;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Success extends \Magento\Framework\App\Action\Action
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
    \Magento\Customer\Model\Session $customerSession,
    \Magento\Checkout\Model\Session $checkoutSession,
    OrderRepositoryInterface $orderRepository,
    \Magento\Framework\Message\ManagerInterface $messageManager
  )
  {
    $this->resultRedirectFactory = $resultRedirectFactory;
    $this->orderRepository = $orderRepository;
    $this->messageManager = $messageManager;
    $this->customerSession = $customerSession;
    $this->scopeConfig = $scopeConfig;
    $this->checkoutSession = $checkoutSession;

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

    $dectaApi->logger->info('Success callback');
    $paymentId = $this->customerSession->getDectaPaymentId();
    $order = $this->checkoutSession->getLastRealOrder();
    $orderId = $order->getEntityId();
    $dectaApi->logger->info('Order id: '. $orderId);
    $orderEntity = $this->orderRepository->get($orderId);
    $resultRedirect = $this->resultRedirectFactory->create();


    if ($dectaApi->was_payment_successful($orderId, $paymentId)) {
      $orderEntity->setState(Order::STATE_COMPLETE);
      $orderEntity->setStatus(Order::STATE_COMPLETE);
      $dectaApi->logger->info('Payment verified, redirecting to success');
      $resultRedirect->setPath('checkout/onepage/success');

      try {
        $this->orderRepository->save($orderEntity);
      }catch (\Exception $e) {
        $dectaApi->logger->info($e);
        $this->messageManager->addExceptionMessage($e, $e->getMessage());
        $resultRedirect->setPath('checkout/onepage/failure');
      }

    } else {
      $dectaApi->log_error('Could not verify payment!');
      $orderEntity->setState(Order::STATE_HOLDED);
      $orderEntity->setStatus(Order::STATE_CANCELED);
      try {
        $this->orderRepository->save($orderEntity);
      }catch (\Exception $e) {
        $dectaApi->logger->info($e);
        $this->messageManager->addExceptionMessage($e, $e->getMessage());
      }
    }

    return $resultRedirect;
  }
}
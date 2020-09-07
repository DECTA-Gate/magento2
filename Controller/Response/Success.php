<?php
namespace Decta\Decta\Controller\Response;

use Decta\Decta\Model\DectaApi;
use Exception;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Action\Action;

class Success extends Action
{
    /**
     * @var ResultFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var DectaApi
     */
    protected $dectaApi;

    /**
     * Success constructor.
     * @param Context $context
     * @param ResultFactory $resultRedirectFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param DectaApi $dectaApi
     */
    public function __construct(
        Context $context,
        ResultFactory $resultRedirectFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        DectaApi $dectaApi
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->dectaApi = $dectaApi;

        parent::__construct($this->context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        $this->dectaApi->logger->info('Success callback');
        $paymentId = $this->customerSession->getDectaPaymentId();
        $order = $this->checkoutSession->getLastRealOrder();
        $orderId = $order->getEntityId();
        $this->dectaApi->logger->info('Order id: '. $orderId);
        $orderEntity = $this->orderRepository->get($orderId);
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($this->dectaApi->wasPaymentSuccessful($orderId, $paymentId)) {
            $orderEntity->setState(Order::STATE_COMPLETE);
            $orderEntity->setStatus(Order::STATE_COMPLETE);
            $this->dectaApi->logger->info('Payment verified, redirecting to success');
            $resultRedirect->setPath('checkout/onepage/success');

            try {
                $this->orderRepository->save($orderEntity);
            } catch (Exception $e) {
                $this->dectaApi->logger->info($e);
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
                $resultRedirect->setPath('checkout/onepage/failure');
            }

        } else {
            $this->dectaApi->log_error('Could not verify payment!');
            $orderEntity->setState(Order::STATE_HOLDED);
            $orderEntity->setStatus(Order::STATE_CANCELED);
            try {
                $this->orderRepository->save($orderEntity);
            } catch (Exception $e) {
                $this->dectaApi->logger->info($e);
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            }
        }

        return $resultRedirect;
    }
}

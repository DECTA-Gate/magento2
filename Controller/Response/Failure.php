<?php
namespace Decta\Decta\Controller\Response;

use Decta\Decta\Model\DectaApi;
use Exception;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;

class Failure extends Action
{
    /**
     * @var ResultFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var DectaApi
     */
    protected $dectaApi;

    /**
     * Failure constructor.
     * @param Context $context
     * @param ResultFactory $resultRedirectFactory
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        ResultFactory $resultRedirectFactory,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        DectaApi $dectaApi
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->context = $context;
        $this->dectaApi = $dectaApi;

        parent::__construct($this->context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderId = $order->getEntityId();
        $orderEntity = $this->orderRepository->get($orderId);
        $orderEntity->setState(Order::STATE_CANCELED);
        $orderEntity->setStatus(Order::STATE_CANCELED);

        try {
            $this->orderRepository->save($orderEntity);
        } catch (Exception $e) {
            $this->dectaApi->logger->info($e);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }
        $this->dectaApi->logger->info('Failure callback');
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/failure');

        return $resultRedirect;
    }
}

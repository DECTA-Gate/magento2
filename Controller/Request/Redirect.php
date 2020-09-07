<?php
namespace Decta\Decta\Controller\Request;

use Exception;
use Magento\Framework\App\Action\Context;
use Decta\Decta\Model\DectaApi;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Action\Action;

class Redirect extends Action
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var DectaApi
     */
    protected $dectaApi;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $urlBuilder
     * @param JsonFactory $resultJsonFactory
     * @param ManagerInterface $messageManager
     * @param OrderRepositoryInterface $orderRepository
     * @param DectaApi $dectaApi
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        UrlInterface $urlBuilder,
        JsonFactory $resultJsonFactory,
        ManagerInterface $messageManager,
        OrderRepositoryInterface $orderRepository,
        DectaApi $dectaApi
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->context = $context;
        $this->dectaApi = $dectaApi;

        parent::__construct($this->context);
    }

    public function execute()
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();

            $successUrl = $this->urlBuilder->getUrl('decta/response/success');
            $failureUrl = $this->urlBuilder->getUrl('decta/response/failure');

            $payment = $this->dectaApi->createPayment($order, $successUrl, $failureUrl);
            $result = $this->resultJsonFactory->create();
            $orderId = $order->getEntityId();
            $orderEntity = $this->orderRepository->get($orderId);

            if (!$payment) {
                $orderEntity->setState(Order::STATE_CANCELED);
                $orderEntity->setStatus(Order::STATE_CANCELED);

                try {
                    $this->orderRepository->save($orderEntity);
                } catch (Exception $e) {
                    $this->dectaApi->logger->info($e->getMessage());
                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                }

                $this->dectaApi->logger->info(sprintf(
                    "Order #%s canceled: Could not load decta payment form",
                    $order->getEntityId()
                ));

                return $result->setData(['url' => $this->urlBuilder->getUrl('checkout/onepage/failure')]);
            }

            $orderEntity->setState(Order::STATE_PROCESSING);
            $orderEntity->setStatus(Order::STATE_PROCESSING);

            try {
                $this->orderRepository->save($orderEntity);
            } catch (Exception $e) {
                $this->dectaApi->logger->info($e->getMessage());
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            }

            $this->customerSession->setDectaPaymentId($payment['id']);
            $this->dectaApi->logger->info('Redirect on full page checkout: ' . $payment['full_page_checkout']);

            return $result->setData(['url' => $payment['full_page_checkout']]);
        } catch (Exception $e) {
            $this->dectaApi->logger->info($e->getMessage());
        }
    }
}

<?php
namespace CyberSource\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Framework\Controller\Result\RedirectFactory;
use CyberSource\Payment\Model\Ui\ConfigProvider;

class Save extends Action
{
    /** @var SessionQuote */
    private $sessionQuote;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var QuoteManagement */
    private $quoteManagement;

    /** @var RedirectFactory */
    private $resultRedirectFactory;

    public function __construct(
        Context $context,
        SessionQuote $sessionQuote,
        CartRepositoryInterface $quoteRepository,
        QuoteManagement $quoteManagement,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->sessionQuote = $sessionQuote;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $quote = $this->sessionQuote->getQuote();
            if (!$quote || !$quote->getId()) {
                $this->messageManager->addErrorMessage(__('Admin quote not found.'));
                return $resultRedirect->setPath('*/*/index');
            }

            // Ensure payment method is set
            $payment = $quote->getPayment();
            if (!$payment || !$payment->getMethod()) {
                $payment->importData(['method' => ConfigProvider::CODE]);
            }

            $quote->setInventoryProcessed(false);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            // Submit quote to create order — payment method's authorize/capture will be invoked
            $order = $this->quoteManagement->submit($quote);

            if ($order && $order->getId()) {
                $this->messageManager->addSuccessMessage(__('The order has been created.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);
            }

            $this->messageManager->addErrorMessage(__('Unable to create order.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error creating order: ') . $e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}

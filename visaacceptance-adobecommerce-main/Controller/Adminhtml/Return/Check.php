<?php
/**
 * Return check for ACS/PA challenge (admin)
 */
declare(strict_types=1);
namespace CyberSource\Payment\Controller\Adminhtml\Return;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Session\StorageInterface;

class Check extends Action
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var StorageInterface */
    private $sessionStorage;

    public function __construct(Context $context, JsonFactory $resultJsonFactory, StorageInterface $sessionStorage)
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->sessionStorage = $sessionStorage;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $transId = $this->sessionStorage->getData('cybersource_return_transaction');
        if ($transId) {
            // clear after reading
            $this->sessionStorage->unsetData('cybersource_return_transaction');
            $result->setData(['success' => true, 'transactionId' => $transId]);
        } else {
            $result->setData(['success' => false]);
        }

        return $result;
    }

    /**
     * ACL check for admin access
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('CyberSource_Payment::manage');
    }
}

<?php

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Adminhtml;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Session\StorageInterface;

class ReturnController extends \Magento\Backend\App\Action implements CsrfAwareActionInterface
{
    private $sessionStorage;

    public function __construct(
        StorageInterface $sessionStorage,
        \Magento\Backend\App\Action\Context $context
    ) {
        parent::__construct($context);
        $this->sessionStorage = $sessionStorage;
    }

    public function execute()
    {
        sleep(1);
        $transId = $this->getRequest()->getParam('TransactionId');
        $this->sessionStorage->setData(['json_data', $transId]);

        return null;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

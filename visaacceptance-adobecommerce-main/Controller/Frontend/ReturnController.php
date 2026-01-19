<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Controller\Frontend;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Session\StorageInterface;

/**
 * CyberSource Return Controller
 */
class ReturnController extends Action implements CsrfAwareActionInterface
{
    /**
     * @var StorageInterface
     */
    private $sessionStorage;

    /**
     * @param StorageInterface $sessionStorage
     * @param Context $context
     */
    public function __construct(
        StorageInterface $sessionStorage,
        Context $context
    ) {
        parent::__construct($context);
        $this->sessionStorage = $sessionStorage;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {   
        sleep(40);
        $transId = $this->getRequest()->getParam('TransactionId');
        $this->sessionStorage->setData(['json_data', $transId]);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

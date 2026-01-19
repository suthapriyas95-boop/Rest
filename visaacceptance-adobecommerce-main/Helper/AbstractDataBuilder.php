<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Helper;

use CyberSource\Payment\Model\LoggerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\GiftMessage\Model\Message as GiftMessage;

abstract class AbstractDataBuilder extends AbstractHelper
{
    public const PARTNER_SOLUTION_ID = 'PMYHJ5VF';
    public const CC_CAPTURE_SERVICE_TOTAL_COUNT = 99;

    /**
     * @var string
     */
    public $merchantId;

    /**
     * @var string
     */
    public $transactionKey;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $customerSession;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    public $checkoutHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Backend\Model\Auth
     */
    protected $auth;

    /**
     * @var GiftMessage
     */
    protected $giftMessage;

    /**
     * @var array
     */
    public $excludedPayerAuthenticationKeys = [
        'payer_authentication_proof_xml',
        'payer_authentication_validate_result',
        'request_token'
    ];

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory
     */
    private $orderGridCollectionFactory;

    /**
     * AbstractDataBuilder constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param \Magento\Checkout\Helper\Data $data
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory $orderGridCollectionFactory
     * @param \Magento\Backend\Model\Auth $auth
     * @param GiftMessage $giftMessage
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Session\SessionManagerInterface $checkoutSession,
        \Magento\Checkout\Helper\Data $data,
        OrderCollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory $orderGridCollectionFactory,
        \Magento\Backend\Model\Auth $auth,
        GiftMessage $giftMessage
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->checkoutHelper  = $data;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderGridCollectionFactory = $orderGridCollectionFactory;
        $this->auth = $auth;
        $this->giftMessage = $giftMessage;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Setup Credentials for webservice
     *
     * @param string $merchantId
     * @param string $transactionKey
     * @return void
     */
    public function setUpCredentials(string $merchantId, string $transactionKey)
    {
        $this->merchantId = $merchantId;
        $this->transactionKey = $transactionKey;
    }

    /**
     * Format amount
     *
     * @param float $amount
     * @return string
     */
    public function formatAmount(float $amount): string
    {
        return sprintf('%.2F', $amount);
    }

    /**
     * Gateway error wrapper
     *
     * @param string $text
     * @return \Magento\Framework\Phrase
     */
    public function wrapGatewayError(string $text): \Magento\Framework\Phrase
    {
        return __('Gateway error: %1', $text);
    }

    /**
     * Return only payer_authentication info from Cybersource Response
     *
     * @param array $request
     * @return array
     */
    public function getPayerAuthenticationData(array $request): array
    {
        $keys = preg_grep("/^(payer_authentication_)/", array_keys($request), 0);

        if (empty($keys)) {
            return [];
        }

        $payerAuthenticationData = [];
        foreach ($keys as $key) {
            if (!in_array($key, $this->excludedPayerAuthenticationKeys)) {
                $payerAuthenticationData[$key] = $request[$key];
            }
        }

        return $payerAuthenticationData;
    }

    /**
     * Build Decision Manager Fields
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return \stdClass
     */
    public function buildDecisionManagerFields(Quote $quote): \stdClass
    {
        $merchantDefinedData = new \stdClass();
        $merchantDefinedData->field1 = (int)$this->customerSession->isLoggedIn(); // Registered or Guest Account

        if ($this->customerSession->isLoggedIn()) {
            /* Account Creation Date */
            $merchantDefinedData->field2 = $this->customerSession->getCustomerData()->getCreatedAt();
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToFilter('customer_id', $this->customerSession->getCustomerId())
                ->setOrder('created_at', 'desc');

            $merchantDefinedData->field3 = count($orders); // Purchase History Count

            if ($orders->getSize() > 0) {
                $lastOrder = $orders->getFirstItem();
                $merchantDefinedData->field4 = $lastOrder->getData('created_at'); // Last Order Date
            }

            $merchantDefinedData->field5 = round(
                (time() - strtotime($this->customerSession->getCustomerData()->getCreatedAt() ?? ''))
                / (3600 * 24)
            );// Member Account Age (Days)
        }

        $orders = $this->orderGridCollectionFactory->create()
            ->addFieldToFilter('customer_email', $quote->getCustomerEmail())
            ->setPageSize(1)
        ;

        $merchantDefinedData->field6 = (int)(count($orders) > 0); // Repeat Customer
        $merchantDefinedData->field20 = $quote->getCouponCode(); //Coupon Code
        $merchantDefinedData->field21 = ($quote->getSubtotal() - $quote->getSubtotalWithDiscount()); // Discount

        $message = $this->giftMessage->load($quote->getGiftMessageId());
        $merchantDefinedData->field22 = ($message) ? $message->getMessage() : ''; // Gift Message
        $merchantDefinedData->field23 = ($this->auth->isLoggedIn()) ? 'call center' : 'web'; //order source

        return $merchantDefinedData;
    }

    /**
     * Get remote address
     *
     * @return string
     */
    public function getRemoteAddress(): string
    {
        return $this->_remoteAddress->getRemoteAddress();
    }
}
 
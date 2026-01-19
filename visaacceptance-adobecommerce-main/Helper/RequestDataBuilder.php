<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Helper;

use CyberSource\Payment\Model\Source\AuthIndicator;
use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Helper\AbstractDataBuilder;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Group;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;

class RequestDataBuilder extends AbstractDataBuilder
{
    public const TAX_AMOUNT = 'merchant_defined_data6';
    public const USE_IFRAME = 'merchant_defined_data11';
    public const REQ_USE_IFRAME = 'req_merchant_defined_data11';
    public const PAY_URL = 'pay_url';
    public const PAY_TEST_URL = 'pay_test_url';
    public const KEY_ORDER_ID = 'merchant_secure_data1';
    public const KEY_QUOTE_ID = 'merchant_secure_data1';
    public const KEY_SID = 'merchant_secure_data2';
    public const KEY_STORE_ID = 'merchant_secure_data3';
    public const KEY_SCOPE = 'merchant_secure_data4';
    public const KEY_AGREEMENT_IDS = 'merchant_defined_data12';
    public const TYPE_SALE = 'sale';
    public const TYPE_AUTHORIZATION = 'authorization';
    public const TYPE_CREATE_TOKEN = 'create_payment_token';
    public const CC_CAPTURE_SERVICE_TOTAL_COUNT = 99;
    public const CARD_TYPE_SELECTION_INDICATOR_BY_CARDHOLDER = '1';

    /**
     * @var Resolver
     */
    private $resolver;

    /**
     * @var string
     */
    private $requestUrl = '';

    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    private $customerModel;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $orderRepository;

    /**
     * @var array
     */
    private $requestUrls = [
        self::PAY_URL => 'https://Payment.visaacceptance.com/pay',
        self::PAY_TEST_URL => 'https://testPayment.visaacceptance.com/pay'
    ];

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory
     */
    private $orderGridCollectionFactory;

    /**
     * @var \Magento\Tax\Model\Config
     */
    private $taxConfig;

    /**
     * RequestDataBuilder constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory $orderGridCollectionFactory
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param Resolver $resolver
     * @param CheckoutHelper $checkoutHelper
     * @param Config $gatewayConfig
     * @param \Magento\Backend\Model\Auth $auth
     * @param \Magento\Customer\Model\Customer $customerModel
     * @param \Magento\GiftMessage\Model\Message $giftMessage
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory $orderGridCollectionFactory,
        \Magento\Framework\Session\SessionManagerInterface $checkoutSession,
        Resolver $resolver,
        CheckoutHelper $checkoutHelper,
        Config $gatewayConfig,
        \Magento\Backend\Model\Auth $auth,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\GiftMessage\Model\Message $giftMessage,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $checkoutHelper,
            $orderCollectionFactory,
            $orderGridCollectionFactory,
            $auth,
            $giftMessage
        );
        $this->resolver = $resolver;
        $this->gatewayConfig = $gatewayConfig;
        $this->customerModel = $customerModel;
        $this->urlBuilder = $context->getUrlBuilder();
        $this->orderRepository = $orderRepository;
        $this->encryptor = $encryptor;
        $this->orderGridCollectionFactory = $orderGridCollectionFactory;
    }

    /**
     * Get card type
     *
     * @param string $code
     * @param bool $isMagentoType
     * @return string
     */
    public function getCardType($code, $isMagentoType = false)
    {
        $types = [
            'VI' => '001',
            'MC' => '002',
            'AE' => '003',
            'DI' => '004',
            'DN' => '005',
            'JCB' => '007',
            'MI' => '042',
            'JW' => '081'
        ];

        if ($isMagentoType) {
            $types = array_flip($types);
        }

        return (isset($types[$code])) ? $types[$code] : $code;
    }

    /**
     * Get card type filter
     *
     * @return array
     */
    public function getCardTypeFilter()
    {
        $filter = [

            "VI" => "VISA",
            "AE" => "AMEX",
            "JCB" => "JCB",
            "MC" => "MASTERCARD"
        ];

        return $filter;
    }

    /**
     * Get checkout method
     *
     * @param Quote $quote
     * @return string
     */
    public function getCheckoutMethod(Quote $quote)
    {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        return $quote->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @param Quote $quote
     * @return void
     */
    public function prepareGuestQuote(Quote $quote)
    {
        $quote->setCustomerId(null);
        $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Filter out empty parameters
     *
     * @param array $params
     * @return array
     */
    public function filterParams($params)
    {
        return array_map(
            function ($value) {
                return (string)$value;
            },
            array_filter(
                $params,
                function ($value) {
                    return $value !== null && $value !== '';
                }
            )
        );
    }

    /**
     * Build delete token request
     *
     * @param PaymentTokenInterface $paymentToken
     *
     * @return \stdClass
     */
    public function buildDeleteTokenRequest(PaymentTokenInterface $paymentToken)
    {
        $tokenDetails = json_decode($paymentToken->getTokenDetails());

        $request = new \stdClass();

        $request->merchantID = $this->gatewayConfig->getMerchantId();

        $request->merchantReferenceCode = $tokenDetails->incrementId;

        $recurringSubscriptionInfo = new \stdClass();

        $recurringSubscriptionInfo->subscriptionID = $paymentToken->getGatewayToken();

        $paySubscriptionDeleteService = new \stdClass();

        $paySubscriptionDeleteService->run = 'true';

        $request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

        $request->paySubscriptionDeleteService = $paySubscriptionDeleteService;

        return $request;
    }

    /**
     * Get discount for all items
     *
     * @return array
     */
    public function getCartItemsDiscount()
    {
        $cartItemDiscount = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get(\Magento\Checkout\Model\Cart::class);
        $items = $cart->getQuote()->getAllItems();
        if (!empty($items)) {
            foreach ($items as $item) {
                $cartItemDiscount[$item->getSku()] = $item->getDiscountAmount();
            }
        }
        return $cartItemDiscount;
    }
}

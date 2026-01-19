<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Response\Rest;

use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Gateway\Helper\SubjectReader;
use CyberSource\Payment\Helper\RequestDataBuilder;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use CyberSource\Payment\Model\PaymentTokenManagement;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class VaultDetailsHandler implements HandlerInterface
{
    public const TOKEN_DATA = "token_data";
    /**
     * @var PaymentTokenFactoryInterface
     */
    protected $paymentTokenFactory;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    protected $paymentExtensionFactory;

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var RequestDataBuilder
     */
    private $helper;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Config $config
     * @param SubjectReader $subjectReader
     * @param RequestDataBuilder $helper
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Config $config,
        SubjectReader $subjectReader,
        RequestDataBuilder $helper,
        \CyberSource\Payment\Model\LoggerInterface $logger
    ) {
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = $this->getValidPaymentInstance($handlingSubject);

        if (!$paymentToken = $this->getVaultPaymentToken($payment)) {
            return;
        }

        $extensionAttributes = $this->getExtensionAttributes($payment);
        $extensionAttributes->setVaultPaymentToken($paymentToken);

        $payment->unsAdditionalInformation(self::TOKEN_DATA);
    }

    /**
     * Get payment extension attributes
     *
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * Get vault payment token from payment
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @return \Magento\Vault\Api\Data\PaymentTokenInterface
     */
    private function getVaultPaymentToken(\Magento\Sales\Api\Data\OrderPaymentInterface $payment)
    {

        $tokenData = $payment->getAdditionalInformation(self::TOKEN_DATA);

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);

        if (empty($tokenData) || $tokenData === null) {
            return $paymentToken;
        }

        $instrumentId = $tokenData['instrument_id'] ?? null;
        $paymentInstrumentId = $tokenData['payment_instrument_id'] ?? null;

        $customerId = $payment->getOrder()->getCustomerId();

        if ($customerId
            && $instrumentId
            && $existingToken = $this->paymentTokenManagement->getCustomerTokenWithInstrumentIdentifier(
                $customerId,
                $instrumentId
            )
        ) {
            $paymentToken = $existingToken;
        }

        $paymentToken->setGatewayToken($tokenData['payment_token']);
        $paymentToken->setExpiresAt($this->getExpirationDate($tokenData['card_expiry_date']));
        $cardType = $tokenData['card_type'];
        $cardType = str_replace(" ", "", $cardType);
        if (ctype_alpha($cardType)) {
            $cardType = $this->getCardTypeFilterWithAMEX()[$cardType];
        } else {
            $cardType = $this->getCardType($cardType, true);
        }
        $resultToken = [
            'title' => $this->config->getVaultTitle(),
            'incrementId' => $payment->getOrder()->getIncrementId(),
            'type' => $cardType,
            'maskedCC' => $tokenData['cc_last4'],
            'expirationDate' => str_replace("-", "/", $tokenData['card_expiry_date']),
            'merchantId' => $this->config->getMerchantId($payment->getOrder()->getStoreId()),
            'instrumentIdentifierID' => $instrumentId,
            'paymentInstrumentIdentifier' => $paymentInstrumentId
        ];

        if (isset($tokenData['card_bin'])) {
            $resultToken['cardBIN'] = $tokenData['card_bin'];
        }

        $paymentToken->setTokenDetails($this->convertDetailsToJSON($resultToken));

        if ($paymentToken->getEntityId()) {
            $this->paymentTokenRepository->save($paymentToken);
        }
        return $paymentToken;
    }

   /**
    * Get expiration date
    *
    * @param string $cardExpiry
    * @return string
    * @throws \InvalidArgumentException
    */

    private function getExpirationDate($cardExpiry)
    {
        if (!is_string($cardExpiry) || !preg_match('/^\d{2}-\d{4}$/', $cardExpiry)) {
            throw new \InvalidArgumentException('Invalid card expiry format. Expected MM-YYYY.');
        }

        $cardExpiry = explode("-", $cardExpiry);
        $expDate = new \DateTime(
            $cardExpiry[1]
            . '-'
            . $cardExpiry[0]
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * Convert payment token details to JSON
     *
     * @param array $details
     * @return string
     */
    private function convertDetailsToJSON($details)
    {
        $json = \Laminas\Json\Json::encode($details);
        return $json ? $json : '{}';
    }

    /**
     * Get valid payment instance from the build subject
     *
     * @param array $buildSubject
     * @return \Magento\Payment\Model\InfoInterface
     */
    protected function getValidPaymentInstance(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        /** @var \Magento\Payment\Model\InfoInterface $payment */
        $payment = $paymentDO->getPayment();

        ContextHelper::assertOrderPayment($payment);

        return $payment;
    }

    /**
     * Check if the token is unique
     *
     * @param string $token
     * @param InfoInterface $payment
     * @return bool
     */
    private function isTokenUnique($token, InfoInterface $payment)
    {
        $customerId = $payment->getOrder()->getCustomerId();
        $methodCode = $payment->getMethodInstance()->getCode();

        return !$this->paymentTokenManagement->getByGatewayToken($token, $methodCode, $customerId);
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

        return (!empty($types[$code])) ? $types[$code] : $code;
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
     * Get card type filter
     *
     * @return array
     */
    public function getCardTypeFilterWithAMEX()
    {
        $filter = array_merge(array_flip($this->getCardTypeFilter()), ["AMERICANEXPRESS" => 'AE']);
        return $filter;
    }
}

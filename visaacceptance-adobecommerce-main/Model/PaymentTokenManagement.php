<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Vault\Api\Data;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\Data\PaymentTokenSearchResultsInterfaceFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use Magento\Vault\Model\PaymentTokenFactory;

class PaymentTokenManagement extends \Magento\Vault\Model\PaymentTokenManagement
{
    public const KEY_CYBERSOURCE_PAYMENT_TOKEN = 'cybersource_token';
    public const KEY_CYBERSOURCE_INSTRUMENT_ID = 'cybersource_instrument_id';
    public const KEY_CYBERSOURCE_PAYMENT_INSTRUMENT_ID = 'cybersource_payment_instrument_id';


    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var \Magento\Vault\Model\PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param PaymentTokenRepositoryInterface $repository
     * @param \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenResourceModel $paymentTokenResourceModel
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param PaymentTokenSearchResultsInterfaceFactory $searchResultsFactory
     * @param EncryptorInterface $encryptor
     * @param DateTimeFactory $dateTimeFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     */
    public function __construct(
        PaymentTokenRepositoryInterface $repository,
        \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenResourceModel $paymentTokenResourceModel,
        PaymentTokenFactory $paymentTokenFactory,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        PaymentTokenSearchResultsInterfaceFactory $searchResultsFactory,
        EncryptorInterface $encryptor,
        DateTimeFactory $dateTimeFactory,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \CyberSource\Payment\Model\LoggerInterface $logger
    ) {
        parent::__construct(
            $repository,
            $paymentTokenResourceModel,
            $paymentTokenFactory,
            $filterBuilder,
            $searchCriteriaBuilder,
            $searchResultsFactory,
            $encryptor,
            $dateTimeFactory,
        );
        $this->dateTimeFactory = $dateTimeFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Searches for all non-expired tokens for specific payment method
     *
     * @param int $customerId
     * @param string $methodCode
     *
     * @return PaymentTokenInterface[]
     */
    public function getAvailableTokens($customerId, $methodCode)
    {
        $customerFilter = [
            $this->filterBuilder->setField(PaymentTokenInterface::CUSTOMER_ID)
                ->setValue($customerId)
                ->create()
        ];
        $methodFilter = [
            $this->filterBuilder->setField(PaymentTokenInterface::PAYMENT_METHOD_CODE)
                ->setValue($methodCode)
                ->create()
        ];
        $isActiveFilter = [
            $this->filterBuilder->setField(PaymentTokenInterface::IS_ACTIVE)
                ->setValue(1)
                ->create()
        ];
        $expiresAtFilter = [
            $this->filterBuilder->setField(PaymentTokenInterface::EXPIRES_AT)
                ->setConditionType('gt')
                ->setValue(
                    $this->dateTimeFactory->create(
                        'now',
                        new \DateTimeZone('UTC')
                    )->format('Y-m-d 00:00:00')
                )
                ->create()
        ];

        $this->searchCriteriaBuilder->addFilters($customerFilter);
        $this->searchCriteriaBuilder->addFilters($methodFilter);
        $this->searchCriteriaBuilder->addFilters($isActiveFilter);
        // add filters to different filter groups in order to filter by AND expression
        $searchCriteria = $this->searchCriteriaBuilder->addFilters($expiresAtFilter)->create();

        return $this->paymentTokenRepository->getList($searchCriteria)->getItems();
    }

    /**
     * Store token into payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $token
     */
    public function storeTokenIntoPayment(\Magento\Payment\Model\InfoInterface $payment, $token)
    {
        $payment->setAdditionalInformation(self::KEY_CYBERSOURCE_PAYMENT_TOKEN, $token);
    }

    /**
     * Get token from payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string|null
     */
    public function getTokenFromPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        $token = $payment->getAdditionalInformation(self::KEY_CYBERSOURCE_PAYMENT_TOKEN);

        if ($token) {
            return $token;
        }

        if ($publicHash = $payment->getAdditionalInformation(PaymentTokenInterface::PUBLIC_HASH)) {
            $token = $this->paymentTokenManagement->getByPublicHash(
                $publicHash,
                $payment->getAdditionalInformation(PaymentTokenInterface::CUSTOMER_ID)
            );
            if ($token) {
                return $token->getGatewayToken();
            }
        }

        if (!$payment->getExtensionAttributes()) {
            return null;
        }

        $extensionAttributes = $payment->getExtensionAttributes();
        if (!$extensionAttributes instanceof \Magento\Sales\Api\Data\OrderPaymentExtension) {
            return null;
        }

        /** @var \Magento\Vault\Model\PaymentToken $vaultPaymentToken */
        $vaultPaymentToken = $extensionAttributes->getVaultPaymentToken();

        if (null !== $vaultPaymentToken && !$vaultPaymentToken->isEmpty()) {
            return $vaultPaymentToken->getGatewayToken();
        }

        return null;
    }

    /**
     * Store instrument id into payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $instrumentId
     */
    public function storeInstrumentIdIntoPayment(\Magento\Payment\Model\InfoInterface $payment, $instrumentId)
    {
        $payment->setAdditionalInformation(self::KEY_CYBERSOURCE_INSTRUMENT_ID, $instrumentId);
    }

    /**
     * Get instrument id from payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string|null
     */
    public function getInstrumentIdFromPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $payment->getAdditionalInformation(self::KEY_CYBERSOURCE_INSTRUMENT_ID);
    }

    /**
     * Store payment instrument id into payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $paymentInstrumentId
     */
    public function storePaymentInstrumentIdIntoPayment(
        \Magento\Payment\Model\InfoInterface $payment,
        $paymentInstrumentId
    ) {
        $payment->setAdditionalInformation(self::KEY_CYBERSOURCE_PAYMENT_INSTRUMENT_ID, $paymentInstrumentId);
    }

    /**
     * Get payment instrument id from payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string|null
     */
    public function getPaymentInstrumentIdIntoPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $payment->getAdditionalInformation(self::KEY_CYBERSOURCE_PAYMENT_INSTRUMENT_ID);
    }

    /**
     * Get customer token with instrument identifier
     *
     * @param int $customerId
     * @param string $instrumentIdentifierId
     *
     * @return PaymentTokenInterface|null
     */
    public function getCustomerTokenWithInstrumentIdentifier($customerId, $instrumentIdentifierId)
    {
        if (!$customerId) {
            $this->logger->info('Customer Id must be provided.');
            throw new \InvalidArgumentException('Something went wrong. Please try again.');
        }

        if (!$instrumentIdentifierId) {
            $this->logger->info('InstrumentIdentifier Id must be provided.');
            throw new \InvalidArgumentException('Something went wrong. Please try again.');
        }

        $tokens = $this->getVisibleAvailableTokens($customerId);

        foreach ($tokens as $token) {
            try {
                $details = $this->serializer->unserialize($token->getTokenDetails());
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            if (($details['instrumentIdentifierID'] ?? null) == $instrumentIdentifierId) {
                return $token;
            }
        }

        return null;
    }
}

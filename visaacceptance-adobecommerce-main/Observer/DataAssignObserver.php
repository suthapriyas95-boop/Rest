<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Observer;

use CyberSource\Payment\Model\Logger;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Checkout\Model\Session;
use CyberSource\Payment\Model\Ui\ConfigProvider;

class DataAssignObserver extends AbstractDataAssignObserver
{
    public const KEY_FLEX_SIGNED_FIELDS = 'signedFields';
    public const KEY_FLEX_SIGNATURE = 'signature';
    public const KEY_FLEX_TOKEN = 'token';
    public const KEY_CARD_TYPE = 'ccType';
    public const KEY_EXP_DATE = 'expDate';
    public const KEY_FLEX_MASKED_PAN = 'maskedPan';

    /**
     * @var  \Magento\Framework\Session\SessionManagerInterface
     */
    protected $session;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Quote\Api\Data\PaymentExtensionInterfaceFactory
     */
    private $extensionFactory;

    /**
     * @var CyberSource\Payment\Model\Logger
     */
    private $logger;

    /**
     * DataAssignObserver constructor.
     *
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param \Magento\Quote\Api\Data\PaymentExtensionInterfaceFactory $extensionFactory
     * @param \CyberSource\Payment\Model\Config $config
     * @param \CyberSource\Payment\Model\Logger $logger
     */

    public function __construct(
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Quote\Api\Data\PaymentExtensionInterfaceFactory $extensionFactory,
        \CyberSource\Payment\Model\Config $config,
        Logger $logger
    ) {
         $this->session = $session;
         $this->extensionFactory = $extensionFactory;
         $this->config = $config;
         $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $this->assignCvv($observer);
        $this->assignExtensionAttributes($observer);
        $this->assignSessionId($observer);
    }

    /**
     * Assign cvv
     *
     * @param Observer $observer
     */
    private function assignCvv($observer)
    {
        $data = $this->readDataArgument($observer);

        // Passing CVN for:
        // stored cards+config CVN enabled
        // or Secure acceptance (i.e. not microform)
        if ((!$this->isVaultCCMethod($data) || !$this->isCvvEnabled())
        ) {
            return;
        }

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $additionalData = new DataObject($additionalData);
        if (!$cvv = $additionalData->getDataByKey('cvv') ?: $additionalData->getDataByKey('vault_cvv')) {
            return;
        }

        $payment = $this->readPaymentModelArgument($observer);
        $payment->setAdditionalInformation('cvv', $cvv);

        $this->session->setData('cvv', $cvv);
    }

    /**
     * Checks if cvv is enabled in config
     *
     * @param DataObject $data
     * @return bool
     */
    private function isVaultCCMethod($data)
    {
        if ($data->getData(PaymentInterface::KEY_METHOD) != ConfigProvider::CC_VAULT_CODE) {
            return false;
        }
        return true;
    }

    /**
     * Check if cvv is enabled in config
     *
     * @return bool
     */
    private function isCvvEnabled()
    {
        return
            $this->config->getValue("enable_cvv") || $this->config->getValue("enable_admin_cvv");
    }

    /**
     * Assign payer_auth_enroll_session_id to order payment additional information
     *
     * @param Observer $observer
     */
    private function assignSessionId($observer)
    {
        $data = $this->readDataArgument($observer);

        if (!$additionalData = $data->getDataByPath(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA)) {
            return;
        }

        if (empty($additionalData['sessionId'])) {
            return;
        };

        $payment = $this->readPaymentModelArgument($observer);
        $payment->setAdditionalInformation('payer_auth_enroll_session_id', $additionalData['sessionId']);
    }

    /**
     * Assign CcaResponse to order payment extension attributes
     *
     * @param Observer $observer
     */
    private function assignExtensionAttributes($observer)
    {
        $data = $this->readDataArgument($observer);

        if (!$additionalData = $data->getDataByPath(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA)) {
            return;
        }

        if (!isset($additionalData['extension_attributes']) ||
            !$additionalData['extension_attributes'] instanceof
            \Magento\Quote\Api\Data\PaymentExtension
        ) {
            return;
        }

        /** @var \Magento\Quote\Api\Data\PaymentExtension $dataExtensionAttributes */
        $dataExtensionAttributes = $additionalData['extension_attributes'];

        if (!$ccaResponse = $dataExtensionAttributes->getCcaResponse()) {
            return;
        }

        $payment = $this->readPaymentModelArgument($observer);
        /** @var \Magento\Quote\Api\Data\PaymentExtensionInterface $paymentExtensionAttributes */
        $paymentExtensionAttributes = $payment->getExtensionAttributes() ?? $this->extensionFactory->create();

        $paymentExtensionAttributes->setCcaResponse($ccaResponse);

        $payment->setExtensionAttributes($paymentExtensionAttributes);
    }
}

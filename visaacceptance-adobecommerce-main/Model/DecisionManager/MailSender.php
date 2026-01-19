<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\DecisionManager;

use Magento\Framework\DataObjectFactory;

class MailSender
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param DataObjectFactory $dataObjectFactory
     * @param \Magento\Framework\App\State $appState
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        DataObjectFactory $dataObjectFactory,
        \Magento\Framework\App\State $appState,
        \CyberSource\Payment\Model\LoggerInterface $logger
    ) {

        $this->scopeConfig = $scopeConfig;
        $this->transportBuilder = $transportBuilder;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->appState = $appState;
        $this->logger = $logger;
    }

    /**
     * Sends email about failed order to the configured email address
     *
     * @param \Magento\Sales\Model\Order $order
     * @param int $storeId
     *
     * @return void
     */
    public function sendFailureEmail(\Magento\Sales\Model\Order $order, $storeId)
    {
        $emailTempVariables = ['order' => $order];

        $sender = $this->scopeConfig->getValue(
            "payment/chcybersource/dm_fail_sender",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $senderName = $this->scopeConfig->getValue(
            "trans_email/ident_" . $sender . "/name",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $senderEmail = $this->scopeConfig->getValue(
            "trans_email/ident_" . $sender . "/email",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $email = $order->getCustomerEmail();
        $postObject = $this->dataObjectFactory->create();
        $postObject->setData($emailTempVariables);
        $sender = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        $emailTemplate = $this->scopeConfig->getValue(
            "payment/chcybersource/dm_fail_template",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $this->appState->emulateAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML, function () {
        });

        try {
            $transport = $this->transportBuilder->setTemplateIdentifier($emailTemplate)
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars(['data' => $postObject])
                ->setFrom($sender)
                ->addTo($email)
                ->setReplyTo($senderEmail)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info("cancel email sent from store id " . $storeId . " to " . $email);
    }
}

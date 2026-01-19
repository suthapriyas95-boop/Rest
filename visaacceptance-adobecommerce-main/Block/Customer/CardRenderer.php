<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Block\Customer;

use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

/**
 * Render information about saved card
 */
class CardRenderer extends AbstractCardRenderer
{
    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var \CyberSource\Payment\Model\LoggerInterface
     */
    private $logger;

    /**
     * @param Template\Context     $context
     * @param CcConfigProvider     $iconsProvider
     * @param Config               $config
     * @param \CyberSource\Payment\Model\LoggerInterface $logger
     * @param array                $data
     */
    public function __construct(
        Template\Context $context,
        CcConfigProvider $iconsProvider,
        Config $config,
        \CyberSource\Payment\Model\LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $iconsProvider, $data);
        $this->gatewayConfig = $config;
        $this->logger = $logger;
    }

    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     *
     * @return boolean
     */

    public function canRender(PaymentTokenInterface $token)
    {
        $result = $token->getPaymentMethodCode() === ConfigProvider::CODE;
        return $result;
    }
    /**
     * Retrieve last 4 digits of the card
     *
     * @return string
     */
    public function getNumberLast4Digits()
    {
        return $this->getTokenDetails()['maskedCC'];
    }

    /**
     * Retrieve expiration date
     *
     * @return string
     */
    public function getExpDate()
    {
        return $this->getTokenDetails()['expirationDate'];
    }

    /**
     * Retrieve merchant id
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getTokenDetails()['merchantId'];
    }
    /**
     * Retrieve merchant id from config
     *
     * @return string
     */
    public function getMerchantIdConfig()
    {

        return $this->gatewayConfig->getMerchantId();
    }

    /**
     * Retrieve icon url for the card
     *
     * @return string
     */
    public function getIconUrl()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    /**
     * Retrieve icon height
     *
     * @return int
     */
    public function getIconHeight()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    /**
     * Retrieve icon width
     *
     * @return int
     */
    public function getIconWidth()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }

    /**
     * Retrieve Payment method name
     *
     * @return string
     */
    public function getPaymentMethodName()
    {
        return $this->gatewayConfig->getTitle();
    }
}

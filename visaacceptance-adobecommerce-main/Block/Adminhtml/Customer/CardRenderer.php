<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
declare(strict_types=1);
namespace CyberSource\Payment\Block\Adminhtml\Customer;
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
    public function canRender(PaymentTokenInterface $token)
    {
        return $token->getPaymentMethodCode() === ConfigProvider::CODE;
    }

    public function getNumberLast4Digits()
    {
        $details = $this->getTokenDetails();
        return $details['maskedCC'] ?? '';
    }

    public function getExpDate()
    {
        $details = $this->getTokenDetails();
        return $details['expirationDate'] ?? '';
    }

    public function getMerchantId()
    {
        $details = $this->getTokenDetails();
        return $details['merchantId'] ?? '';
    }

    public function getMerchantIdConfig()
    {
        return $this->gatewayConfig->getMerchantId();
    }

    public function getIconUrl()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    public function getIconHeight()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    public function getIconWidth()
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }

    public function getPaymentMethodName()
    {
        return $this->gatewayConfig->getTitle();
    }
}

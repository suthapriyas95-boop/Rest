<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

class CvnBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const ADMIN_PREFIX = 'admin';
    private const CARD_TYPE_SELECTION_INDICATOR_BY_CARDHOLDER = '1';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $session;

    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var string
     */
    private $isAdmin;

    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param \CyberSource\Payment\Model\Config $config
     * @param string|null $isAdmin
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \CyberSource\Payment\Model\Config $config,
        ?string $isAdmin = null,
    ) {
        $this->subjectReader = $subjectReader;
        $this->session = $session;
        $this->config = $config;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Builds Subscription data request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $result = [];
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        if (!$cvv = $payment->getAdditionalInformation('cvv') ?? $this->session->getData('cvv')) {
            return [];
        }

        $prefix = '';

        if ($this->isAdmin) {
            $prefix = self::ADMIN_PREFIX . '_';
        }

        $path = 'enable_' . $prefix . 'cvv';

        if ($this->config->getValue($path)) {
            $result['paymentInformation']['card']['securityCode'] = $cvv;
        }
        $result['paymentInformation']['card']['typeSelectionIndicator'] =
            self::CARD_TYPE_SELECTION_INDICATOR_BY_CARDHOLDER;
        $payment->unsAdditionalInformation('cvv');

        return $result;
    }
}

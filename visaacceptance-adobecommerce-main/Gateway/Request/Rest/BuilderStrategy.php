<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Model\Logger;

class BuilderStrategy implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Payment\Gateway\Request\BuilderInterface
     */
    private $microformBuilder;

    /**
     * @var \Magento\Payment\Gateway\Request\BuilderInterface
     */
    private $standardBuilder;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param \CyberSource\Payment\Model\Config $config
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \Magento\Payment\Gateway\Request\BuilderInterface $microformBuilder
     * @param \Magento\Payment\Gateway\Request\BuilderInterface $standardBuilder
     * @param Logger $logger
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \Magento\Payment\Gateway\Request\BuilderInterface $microformBuilder,
        \Magento\Payment\Gateway\Request\BuilderInterface $standardBuilder,
        Logger $logger
    ) {
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->microformBuilder = $microformBuilder;
        $this->standardBuilder = $standardBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws \Magento\Payment\Gateway\Exception\InvalidArgumentException
     */
    public function build(array $buildSubject)
    {

        $payment = $this->subjectReader->readPayment($buildSubject)->getPayment();

        // always use the standard builder for vault

        if ($payment->getMethod() == \CyberSource\Payment\Model\Ui\ConfigProvider::CC_VAULT_CODE) {
            return $this->standardBuilder->build($buildSubject);
        }
        return $this->microformBuilder->build($buildSubject);
    }
}

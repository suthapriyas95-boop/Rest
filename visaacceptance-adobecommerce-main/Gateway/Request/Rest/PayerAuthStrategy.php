<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Gateway\Request\Rest\PayerAuthEnrollBuilder;
use CyberSource\Payment\Gateway\Request\Rest\PayerAuthValidateBuilder;
use Magento\Framework\Url\DecoderInterface;

class PayerAuthStrategy implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * @var \CyberSource\Payment\Helper\RequestDataBuilder
     */
    private $requestDataBuilder;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var PayerAuthEnrollBuilder
     */
    private $enrollBuilder;

    /**
     * @var PayerAuthvalidateBuilder
     */
    private $validateBuilder;

    /**
     * @var \Magento\Framework\Session\StorageInterface
     */
    private $sessionStorage;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\Url\DecoderInterface
     */
    protected $urlDecoder;

    /**
     * PayerAuthStrategy constructor.
     *
     * @param \CyberSource\Payment\Model\Config $config
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Helper\RequestDataBuilder $requestDataBuilder
     * @param PayerAuthEnrollBuilder $enrollBuilder
     * @param PayerAuthvalidateBuilder $validateBuilder
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config,
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Helper\RequestDataBuilder $requestDataBuilder,
        PayerAuthEnrollBuilder $enrollBuilder,
        PayerAuthvalidateBuilder $validateBuilder,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Checkout\Model\Session $checkoutSession,
        DecoderInterface $urlDecoder
    ) {
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->requestDataBuilder = $requestDataBuilder;
        $this->enrollBuilder = $enrollBuilder;
        $this->validateBuilder = $validateBuilder;
        $this->sessionStorage = $sessionStorage;
        $this->checkoutSession = $checkoutSession;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Builds payer auth request
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {
        $quote = $this->checkoutSession->getQuote();
        $token = $quote->getPayment()->getAdditionalInformation("paymentToken");
        if ($token) {
            $decoded_transient_token = json_decode(
                $this->urlDecoder->decode(
                    explode('.', base64_decode($token))[1]
                ),
                true
            );
        }
        if (!$this->config->isPayerAuthEnabled() ||
            (isset($decoded_transient_token['content']['processingInformation']['paymentSolution']) &&
                ($decoded_transient_token['content']['processingInformation']['paymentSolution']['value'] === '027'
                )) ||(isset($decoded_transient_token['content']['processingInformation']['paymentSolution'])
                        && $decoded_transient_token['content']['processingInformation']['paymentSolution']['value'] ===
                        '012' && $decoded_transient_token['metadata']['cardholderAuthenticationStatus'] === true)
                       
        ) {
            return [];
        }
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        /** @var \Magento\Quote\Api\Data\PaymentInterface $payment */
        $payment = $paymentDO->getPayment();
        $extensionAttributes = $payment->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getCcaResponse()) {
            return $this->validateBuilder->build($buildSubject);
        }

        return $this->enrollBuilder->build($buildSubject);
    }
}

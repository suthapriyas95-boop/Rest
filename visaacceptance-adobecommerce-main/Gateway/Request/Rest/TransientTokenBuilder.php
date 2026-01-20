<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Framework\Url\DecoderInterface;
use CyberSource\Payment\Gateway\Helper\SubjectReader;

class TransientTokenBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\Url\DecoderInterface
     */
    protected $urlDecoder;

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        DecoderInterface $urlDecoder,
        SubjectReader $subjectReader
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->urlDecoder = $urlDecoder;
        $this->subjectReader = $subjectReader;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $payment = null;
        try {
            $paymentDO = $this->subjectReader->readPayment($buildSubject);
            $payment = $paymentDO->getPayment();
        } catch (\InvalidArgumentException $e) {
            $payment = null;
        }

        if ($payment === null) {
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote ? $quote->getPayment() : null;
        }

        $paymentToken = $payment ? $payment->getAdditionalInformation('paymentToken') : null;

        $request = [];
        if ($paymentToken) {
            $request['tokenInformation'] = [
                'transientTokenJwt' => $this->urlDecoder->decode($paymentToken)
            ];
        }

        return $request;
    }
}

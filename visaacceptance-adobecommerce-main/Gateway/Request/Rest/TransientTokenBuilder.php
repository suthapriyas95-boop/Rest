<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Framework\Url\DecoderInterface;

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
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        DecoderInterface $urlDecoder
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();
        $paymentToken = $payment->getAdditionalInformation('paymentToken');

        $request = [];
        if ($paymentToken) {
            $request['tokenInformation'] = [
                'transientTokenJwt' => $this->urlDecoder->decode($paymentToken)
            ];
        }

        return $request;
    }
}

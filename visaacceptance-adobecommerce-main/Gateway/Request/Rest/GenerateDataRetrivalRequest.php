<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Url\DecoderInterface;

class GenerateDataRetrivalRequest implements BuilderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

     /**
      * @var \Magento\Framework\Url\DecoderInterface
      */
    protected $urlDecoder;

    /**
     * GenerateDataRetrivalRequest constructor.
     * @param Session $checkoutSession
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        Session $checkoutSession,
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
        $token = $quote->getPayment()->getAdditionalInformation("paymentToken");
        return ['url_params' => [$this->urlDecoder->decode($token)]];
    }
}

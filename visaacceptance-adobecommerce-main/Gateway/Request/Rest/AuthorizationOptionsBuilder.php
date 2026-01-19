<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Checkout\Model\Session;
use Magento\Framework\Url\DecoderInterface;

class AuthorizationOptionsBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const KEY_AUTHORIZE_OPTIONS = 'authorizationOptions';
    private const KEY_IGNORE_AVS_RESULT = 'ignoreAVSResult';
    private const KEY_IGNORE_CV_RESULT = 'ignoreCvResult';

    /**
     * @var Session
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
        Session $checkoutSession,
        DecoderInterface $urlDecoder
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $quote = $this->checkoutSession->getQuote();
        $token = $this->urlDecoder->decode($quote->getPayment()->getAdditionalInformation("paymentToken"));
        return [
            'tokenInformation' => [
                'transientTokenJwt' => $token,
            ]
        ];
    }
}

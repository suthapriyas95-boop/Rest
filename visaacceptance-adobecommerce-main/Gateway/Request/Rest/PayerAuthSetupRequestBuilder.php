<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Framework\Url\DecoderInterface;

class PayerAuthSetupRequestBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const TRANSIENTTOKEN = 'transientToken';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var  \CyberSource\Payment\Model\PaymentTokenManagement
     */

    private $paymentTokenManagement;
     /**
      *
      * @var \Magento\Checkout\Model\Session
      */
    private $checkoutSession;

     /**
      * @var \Magento\Framework\Url\DecoderInterface
      */
    protected $urlDecoder;

    /**
     * PayerAuthSetupRequestBuilder constructor.
     *
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Url\DecoderInterface $urlDecoder
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        \CyberSource\Payment\Model\PaymentTokenManagement $paymentTokenManagement,
        \Magento\Checkout\Model\Session $checkoutSession,
        DecoderInterface $urlDecoder
    ) {
        $this->subjectReader            = $subjectReader;
        $this->paymentTokenManagement   = $paymentTokenManagement;
        $this->checkoutSession          = $checkoutSession;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Builds JTW token data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $token = $this->paymentTokenManagement->getTokenFromPayment($payment);
        $quote = $this->checkoutSession->getQuote();
        $quote->reserveOrderId();

        if (!empty($token)) {
            $result = [
                'clientReferenceInformation' => [
                    'code'  => $quote->getReservedOrderId(),
                ],
                'paymentInformation' => [
                    'customer' => [
                        'customerId' => $token,
                    ]

                ]
            ];
        }

        if (!empty($payment->getAdditionalInformation("paymentToken"))) {
            $transientTokenJwttest = $this->urlDecoder->decode($payment->getAdditionalInformation("paymentToken"));
            $ttBody = explode('.', $transientTokenJwttest)[1];
            $ttdecode = $this->urlDecoder->decode($ttBody);
            $json = json_decode($ttdecode);
            $jti = $json->jti;
            $result = [
                'tokenInformation' => [
                    'transientToken' => $jti,
                ],
                'clientReferenceInformation' => [
                    'code'  => $quote->getReservedOrderId(),
                    ]
            ];
        }

        return $result;
    }
}

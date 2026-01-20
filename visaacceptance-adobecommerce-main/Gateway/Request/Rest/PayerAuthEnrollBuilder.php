<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Model\Config;
use CyberSource\Payment\Gateway\Validator\PaEnrolledValidator;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class PayerAuthEnrollBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    public const KEY_CONSUMER_AUTHENTICATION = 'CONSUMER_AUTHENTICATION';
    public const DEVICE_CHANEL = 'Browser';
    public const KEY_DECISION_SKIP = 'DECISION_SKIP';

    /**
     * @var \CyberSource\Payment\Gateway\Helper\SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Framework\App\RequestInterface $request
     */
    private $request;

    /**
     * @var \CyberSource\Payment\Model\Config $config
     */
    private $config;

    /**
     * @var \Magento\Framework\Session\StorageInterface $sessionStorage
     */
    private $sessionStorage;

    /**
     * @var  \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     */
    private $remoteAddress;

    /**
     * @var \Magento\Framework\UrlInterface $url
     */
    private $url;

    /**
     * @var PaenrolledValidator $paenrolledValidator
     */
    private $paenrolledValidator;

    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @param \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader
     * @param Config $config
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Session\StorageInterface $sessionStorage
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     * @param \Magento\Framework\UrlInterface $url
     * @param PaenrolledValidator $paenrolledValidator
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \CyberSource\Payment\Gateway\Helper\SubjectReader $subjectReader,
        Config $config,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Session\StorageInterface $sessionStorage,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\UrlInterface $url,
        PaenrolledValidator $paenrolledValidator,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->subjectReader = $subjectReader;
        $this->request = $request;
        $this->config = $config;
        $this->sessionStorage = $sessionStorage;
        $this->remoteAddress = $remoteAddress;
        $this->url = $url;
        $this->paenrolledValidator = $paenrolledValidator;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Builds the payer auth enroll request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $quote = $this->checkoutSession->getQuote();
        $browserDetails = $this->sessionStorage->getData('browser_details');
        $referenceId = $this->sessionStorage->getData('referenceId');
        $payerAuthEnrollService_returnURL = $this->url->getUrl('cybersourcePayment/Frontend/ReturnController/');
        $requestArr = [
            'consumerAuthenticationInformation' => [
                'referenceID' => $referenceId,
                'returnUrl' => $payerAuthEnrollService_returnURL,
                'deviceChannel' => self::DEVICE_CHANEL,
            ],
        'deviceInformation' => [
                'httpBrowserJavaEnabled' => $browserDetails['JavaEnabled'],
                'httpAcceptBrowserValue' => $this->request->getHeader('Accept'),
                'httpBrowserLanguage' => $browserDetails['Language'],
                'httpBrowserColorDepth' => $browserDetails['ScreenHeight'],
                'httpBrowserScreenHeight' => $browserDetails['ScreenHeight'],
                'httpBrowserScreenWidth' => $browserDetails['ScreenWidth'],
                'httpBrowserTimeDifference' => $browserDetails['TimeDifference'],
                'userAgentBrowserValue' => $this->request->getHeader('User-Agent'),
                'ipAddress' => $this->remoteAddress->getRemoteAddress(),
                'httpBrowserJavaScriptEnabled' => $browserDetails['JavaScriptEnabled'],
                'httpAcceptContent' => $this->request->getHeader('User-Agent'),
            ]
        ];

        if (!$payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE)) {
            $requestArr['processingInformation'] =  [
                "actionList" => [self::KEY_CONSUMER_AUTHENTICATION]
            ];
        } else {
            $requestArr['processingInformation'] =  [
               "actionList" => [self::KEY_CONSUMER_AUTHENTICATION, Config::KEY_TOKEN_CREATE],
               "actionTokenTypes" => [
                   Config::KEY_PAYMENT_INSTRUMENT, Config::KEY_INSTRUMENT_IDENTIFIER, Config::KEY_CUSTOMER
               ],
               'authorizationOptions' => [
                   'initiator' => [
                       'credentialStoredOnFile' => true,
                   ]
               ]
            ];
        }

            $requestArr['processingInformation']['actionList'][] = self::KEY_DECISION_SKIP;


        if ($this->isScaRequired($payment)) {
            $requestArr['consumerAuthenticationInformation']['challengeCode'] = '04';
        }

        return $requestArr;
    }

        /**
         * Returns whether Strong Customer Authentication is required or not
         *
         * @param \Magento\Payment\Model\InfoInterface $payment
         * @return boolean
         */
    private function isScaRequired($payment)
    {
        $result = false;

        if (($payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE)) &&
            ($this->config->isScaEnforcedOnCardSave() == "1")
        ) {
            $result = true;
        }

        if ($this->sessionStorage->getData(
            \CyberSource\Payment\Gateway\Validator\PaEnrolledValidator::KEY_SCA_REQUIRED
        )
        ) {
            $result = true;
        }
        return $result;
    }
}

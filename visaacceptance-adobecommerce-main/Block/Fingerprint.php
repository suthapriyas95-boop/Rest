<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Block;

class Fingerprint extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var string
     */
    private $sessionId;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->sessionId = $this->checkoutSession->getQuote()->getId() . time();

        parent::__construct($context, $data);
    }

    /**
     * Return js url
     *
     * @return string
     */
    public function getJsUrl()
    {
        return 'https://h.online-metrix.net/fp/tags.js?' . $this->composeUrlParams();
    }

    /**
     * Return iframe url
     *
     * @return string
     */
    public function getIframeUrl()
    {
        return 'https://h.online-metrix.net/fp/tags?' . $this->composeUrlParams();
    }

    /**
     * Return org id from configuration
     *
     * @return string|null
     */
    public function getOrgId()
    {
        $orgId = $this->_scopeConfig->getValue(
            "payment/chcybersource/org_id",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($orgId !== null || $orgId !== "") {
            return $orgId;
        }

        return null;
    }

    /**
     * Compose url parameters
     *
     * @return string
     */
    private function composeUrlParams()
    {
        $orgId = $this->getOrgId();

        if ($this->isFingerprintEnabled()) {
            $this->checkoutSession->setFingerprintId($this->sessionId);
            return 'org_id=' . $orgId . '&session_id=' . $this->sessionId;
        } else {
            $this->checkoutSession->setFingerprintId(null);
            return 'session_id=' . $this->sessionId;
        }
    }

    /**
     * Check if fingerprint is enabled
     *
     * @return bool
     */
    public function isFingerprintEnabled()
    {
        return $this->_scopeConfig->getValue(
            "payment/chcybersource/fingerprint_enabled",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}

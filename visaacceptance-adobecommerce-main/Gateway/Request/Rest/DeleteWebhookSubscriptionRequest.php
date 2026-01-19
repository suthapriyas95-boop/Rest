<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Session\SessionManagerInterface;

class DeleteWebhookSubscriptionRequest implements BuilderInterface
{
    /**
     * Builds DELETE request
     *
     * @param array $buildSubject
     * @return array
     */
     /**
      * @var SessionManagerInterface
      */
    private $session;

    /**
     * Constructor
     *
     * @param SessionManagerInterface $session
     */
    public function __construct(SessionManagerInterface $session)
    {
        $this->session = $session;
    }
    public function build(array $buildSubject)
    {
        $webhookId = $this->session->getData('webhookid_from_all_webhook_request');


        if ($webhookId === null) {
            throw new \InvalidArgumentException(
                'Webhook ID is not set in the session.'
            );
        }
        return ['url_params' => [$webhookId]];
    }
}
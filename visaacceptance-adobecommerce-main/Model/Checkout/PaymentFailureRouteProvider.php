<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Checkout;

class PaymentFailureRouteProvider implements PaymentFailureRouteProviderInterface
{
    /**
     * @var \CyberSource\Core\Model\Config
     */
    private $config;

    /**
     * @var string
     */
    private $defaultPaymentFailureRoute;

    /**
     * PaymentFailureRouteProvider constructor.
     *
     * @param \CyberSource\Core\Model\Config $config
     * @param string $defaultPaymentFailureRoute
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config,
        $defaultPaymentFailureRoute = 'checkout/cart'
    ) {
        $this->defaultPaymentFailureRoute = $defaultPaymentFailureRoute;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getFailureRoutePath()
    {

        return $this->defaultPaymentFailureRoute;
    }
}

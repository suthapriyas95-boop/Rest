<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model\Checkout;

interface PaymentFailureRouteProviderInterface
{
    /**
     * Returns the route path that the customer will be redirected on checkout payment failures
     *
     * @return string Failure page route path
     */
    public function getFailureRoutePath();
}

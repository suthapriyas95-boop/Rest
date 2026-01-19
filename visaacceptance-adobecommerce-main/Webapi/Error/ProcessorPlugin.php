<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Webapi\Error;

class ProcessorPlugin
{
    /**
     * Before mask exception
     *
     * @param \Magento\Framework\Webapi\ErrorProcessor $subject
     * @param \Exception $e
     * @return array
     */
    public function beforeMaskException(\Magento\Framework\Webapi\ErrorProcessor $subject, \Exception $e)
    {
        $previousException = $e->getPrevious();
        if ($previousException && $previousException instanceof \CyberSource\Payment\Gateway\PaEnrolledException) {
            return [$previousException];
        }
        return [$e];
    }
}

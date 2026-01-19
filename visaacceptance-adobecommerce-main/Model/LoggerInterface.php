<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Model;

interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * Logs with an arbitrary level.
     *
     * @param array|string $data
     * @param array $context
     */
    public function debug($data, array $context = []): void;
}

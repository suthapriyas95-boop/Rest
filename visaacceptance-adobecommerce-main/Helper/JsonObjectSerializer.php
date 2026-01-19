<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Helper;

class JsonObjectSerializer implements \Magento\Framework\Serialize\SerializerInterface
{
    /**
     * Serialize data into string
     *
     * @param string|int|float|bool|array|null $data
     *
     * @return string|bool
     * @throws \InvalidArgumentException
     * @since 100.2.0
     */
    public function serialize($data)
    {
        $result = json_encode($data);

        return $result;
    }

    /**
     * Unserialize the given string
     *
     * @param string $string
     *
     * @return string|int|float|bool|array|null
     * @throws \InvalidArgumentException
     * @since 100.2.0
     */
    public function unserialize($string)
    {
        $result = json_decode($string, false);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        return $result;
    }
}

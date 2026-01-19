<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Plugin;

class MinificationPlugin
{
    /**
     * @var array
     */
    private $additionalExcludes = [];

    /**
     * @param array $additionalExcludes
     */
    public function __construct(array $additionalExcludes = [])
    {
        $this->additionalExcludes = $additionalExcludes;
    }

    /**
     * Excludes specified files from minification
     *
     * @param  \Magento\Framework\View\Asset\Minification $subject
     * @param  callable $proceed
     * @param  array $contentType
     * @return array $result
     */
    public function aroundGetExcludes(
        \Magento\Framework\View\Asset\Minification $subject,
        callable $proceed,
        $contentType
    ) {
        $result = $proceed($contentType);
        if ($contentType != 'js') {
            return $result;
        }
        $result = array_merge($result, $this->additionalExcludes);
        return $result;
    }
}

<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\ErrorMapper;

class ConfigurableMapper implements \Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface
{
    /**
     * @var \CyberSource\Payment\Model\Config
     */
    private $config;

    /**
     * ConfigurableMapper constructor.
     *
     * @param \CyberSource\Payment\Model\Config $config
     */
    public function __construct(
        \CyberSource\Payment\Model\Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Returns customized error message by provided code.
     *
     * @param string $code
     * @return \Magento\Framework\Phrase|null
     */
    public function getMessage(string $code)
    {
        if (!empty($code)) {
            return __($code);
        }

        return __('Transaction has been declined. Please try again later.');
    }
}

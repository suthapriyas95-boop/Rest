<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Gateway\Request\Rest;

use CyberSource\Payment\Model\Config;

class TimeIntervalBuilder implements \Magento\Payment\Gateway\Request\BuilderInterface
{
    private const REPORT_INTERVAL = 23 * 3600;
    private const DATE_FORMAT = 'c';

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;
    /**
     * @var Config
     */
    /**
     * @var Config
     */
    private $config;

    /**
     * TimeIntervalBuilder constructor.
     *
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param Config $config
     */
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        Config $config
    ) {
        $this->dateTime = $dateTime;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $interval = $buildSubject['interval'] ?? static::REPORT_INTERVAL;

        if ($buildSubject['startTime'] !== null) {
            $startDateTime = $this->dateTime->gmtDate(static::DATE_FORMAT, $buildSubject['startTime']);
            $calculatedStartTime = $this->dateTime->gmtTimestamp($startDateTime);
            $endDateTime = $this->dateTime->gmtDate(static::DATE_FORMAT, $calculatedStartTime + $interval);
        } else {
            $gmtTimestamp = $this->dateTime->gmtTimestamp();
            $startDateTime = $buildSubject['startTime'] ?? $this->dateTime->gmtDate(
                static::DATE_FORMAT,
                $gmtTimestamp - $interval
            );
            $endDateTime = $buildSubject['endTime'] ?? $this->dateTime->gmtDate(static::DATE_FORMAT, $gmtTimestamp);
        }

        return [
            'startTime' => $startDateTime,
            'endTime' => $endDateTime,
        ];
    }
}

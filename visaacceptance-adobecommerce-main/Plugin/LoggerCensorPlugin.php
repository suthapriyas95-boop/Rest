<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Plugin;

use CyberSource\Payment\Model\Logger;
use CyberSource\Payment\Model\LoggerInterface;

class LoggerCensorPlugin
{
    /**
     * @var Logger\Censor
     */
    private $censor;

    /**
     * @param Logger\Censor $censor
     */
    public function __construct(Logger\Censor $censor)
    {
        $this->censor = $censor;
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param mixed $data
     * @param array $context
     * @return array
     */
    public function beforeDebug(LoggerInterface $subject, $data, array $context = [])
    {
        return [$this->censor->censor($data), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeEmergency(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeAlert(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeCritical(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeError(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeWarning(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeNotice(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeInfo(LoggerInterface $subject, $message, array $context = [])
    {
        return [$this->censor->censor($message), $context];
    }

    /**
     * Plugin for censoring sensitive data in logs
     *
     * @param LoggerInterface $subject
     * @param string $level
     * @param string $message
     * @param array $context
     * @return array
     */
    public function beforeLog(LoggerInterface $subject, $level, $message, array $context = [])
    {
        return [$level, $this->censor->censor($message), $context];
    }
}

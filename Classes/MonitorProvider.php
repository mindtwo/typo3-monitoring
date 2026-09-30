<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3;

use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * Shared container service that builds the Monitor once per process, so the
 * console commands and the pull middleware work on the same instance.
 */
final class MonitorProvider
{
    private ?Monitor $monitor = null;

    public function __construct(private Typo3Api $typo3) {}

    public function monitor(): Monitor
    {
        return $this->monitor ??= MonitorFactory::make($this->typo3);
    }
}

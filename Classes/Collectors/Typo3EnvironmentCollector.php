<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Collectors;

use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Typo3\Support\EnvironmentName;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * Operational state of the TYPO3 installation — debug output or a development
 * context in production is the classic misconfiguration worth alerting on.
 * Both the raw application context and the derived environment name are
 * reported, so a mismatch is visible on the dashboard.
 */
final class Typo3EnvironmentCollector extends AbstractCollector
{
    public function __construct(private Typo3Api $typo3) {}

    public function key(): string
    {
        return 'typo3_environment';
    }

    public function collect(): CollectionResult
    {
        $context = $this->typo3->applicationContext();
        $displayErrors = $this->typo3->configuration('SYS/displayErrors');

        return CollectionResult::ok($this->key(), [
            'context' => $context,
            'environment' => EnvironmentName::fromContext($context),
            'composer_mode' => $this->typo3->isComposerMode(),
            'display_errors' => is_numeric($displayErrors) ? (int) $displayErrors : null,
            'backend_debug' => (bool) $this->typo3->configuration('BE/debug'),
            'frontend_debug' => (bool) $this->typo3->configuration('FE/debug'),
        ]);
    }
}

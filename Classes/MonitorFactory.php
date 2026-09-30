<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3;

use Mindtwo\Monitoring\Collectors\DefaultCollectors;
use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\SnapshotBuilder;
use Mindtwo\Monitoring\SnapshotFactory;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Mindtwo\Monitoring\Transport\HttpTransport;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3Collector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3DatabaseCollector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3EnvironmentCollector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3ExtensionsCollector;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;
use Mindtwo\Monitoring\Typo3\Support\Typo3ConfigurationRepository;

/**
 * Assembles a fully wired Monitor for this TYPO3 installation: the base
 * collector catalog against the project root plus the TYPO3 collectors, with
 * the HTTP push transport from the configuration.
 */
final class MonitorFactory
{
    public const PACKAGE = 'mindtwo/typo3-monitoring';

    public static function make(Typo3Api $typo3, ?Typo3ConfigurationRepository $config = null): Monitor
    {
        $config ??= new Typo3ConfigurationRepository($typo3);

        $projectRoot = $typo3->projectPath() ?? (string) getcwd();
        $projectKey = $config->credentials()->projectKey;

        $factory = new SnapshotFactory(
            Source::plugin(Source::TYPE_TYPO3, self::PACKAGE),
            $config->environment(),
            $projectKey !== '' ? $projectKey : null
        );

        $transport = new HttpTransport(
            $config->endpoint(),
            $config->credentials(),
            new HmacRequestSigner,
            max(1, $config->integer('timeout'))
        );

        $monitor = new Monitor(new SnapshotBuilder($factory), $transport);

        $monitor->replace(...DefaultCollectors::make(projectRoot: $projectRoot));
        $monitor->replace(
            new Typo3Collector($typo3),
            new Typo3ExtensionsCollector($typo3),
            new Typo3DatabaseCollector($typo3),
            new Typo3EnvironmentCollector($typo3),
        );

        return $monitor;
    }

    private function __construct()
    {
        // Static factory — never instantiated.
    }
}

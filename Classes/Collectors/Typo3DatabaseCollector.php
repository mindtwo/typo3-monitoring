<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Collectors;

use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Support\DatabaseVersion;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;
use Mindtwo\Monitoring\Typo3\Support\DatabaseDriver;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * The live database server version from the TYPO3 default connection —
 * replacing the base package's CLI client detection.
 */
final class Typo3DatabaseCollector extends AbstractCollector
{
    private TechnologyResolver $technologies;

    public function __construct(
        private Typo3Api $typo3,
        ?TechnologyResolver $technologies = null
    ) {
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'database';
    }

    public function collect(): CollectionResult
    {
        $detected = $this->typo3->databaseVersion();

        if ($detected === null) {
            return CollectionResult::failed($this->key(), 'Unable to inspect the TYPO3 database connection.');
        }

        [$driver, $rawVersion] = $detected;
        [$identifier, $version] = DatabaseVersion::normalize(DatabaseDriver::normalize($driver), $rawVersion);

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve($identifier),
            $version,
            ['detected_via' => 'connection', 'driver' => $driver]
        ));
    }
}

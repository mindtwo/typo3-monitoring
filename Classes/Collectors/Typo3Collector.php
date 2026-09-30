<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Collectors;

use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * TYPO3 itself as a first-class technology metric, resolved to the
 * endoflife.date slug `typo3` so the dashboard matches the release cycle.
 */
final class Typo3Collector extends AbstractCollector
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
        return 'typo3';
    }

    public function unsupportedReason(): ?string
    {
        return $this->typo3->version() !== null
            ? null
            : 'The TYPO3 version is unavailable — is TYPO3 bootstrapped?';
    }

    public function collect(): CollectionResult
    {
        $version = $this->typo3->version();

        if ($version === null) {
            return CollectionResult::unsupported($this->key(), $this->unsupportedReason());
        }

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve('typo3'),
            $version,
            [
                'branch' => $this->branch($version),
                'composer_mode' => $this->typo3->isComposerMode(),
            ]
        ));
    }

    /**
     * The release branch ("13.4" for "13.4.28"), which is what TYPO3 calls a
     * maintained version line.
     */
    private function branch(string $version): ?string
    {
        return preg_match('/^(\d+\.\d+)/', $version, $matches) === 1 ? $matches[1] : null;
    }
}

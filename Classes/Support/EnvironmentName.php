<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

/**
 * Derives the snapshot's `environment` name from a TYPO3 application context
 * such as "Production", "Production/Staging" or "Development/Local". The root
 * context is fixed by TYPO3 (Development, Production, Testing); sub-contexts
 * are free-form, so only the common staging convention is recognised.
 */
final class EnvironmentName
{
    public const DEFAULT = 'production';

    private const ROOTS = ['development', 'production', 'testing'];

    private const STAGING_SEGMENTS = ['staging', 'stage'];

    public static function fromContext(?string $context): string
    {
        if ($context === null || trim($context) === '') {
            return self::DEFAULT;
        }

        $segments = array_map(
            static fn (string $segment): string => strtolower(trim($segment)),
            explode('/', $context)
        );

        $root = array_shift($segments);

        if (! in_array($root, self::ROOTS, true)) {
            return self::DEFAULT;
        }

        if ($root === 'production' && array_intersect($segments, self::STAGING_SEGMENTS) !== []) {
            return 'staging';
        }

        return $root;
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}

<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

/**
 * In composer mode TYPO3 reports composer's pretty versions ("v13.4.28",
 * "dev-main", "1.0.0"). Strip the tag prefix so versions compare cleanly;
 * branch versions pass through untouched.
 */
final class ExtensionVersion
{
    public static function normalize(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim($version);

        if ($version === '') {
            return null;
        }

        return (string) preg_replace('/^v(?=\d)/i', '', $version);
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}

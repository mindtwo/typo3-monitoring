<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

/**
 * Maps TYPO3's Doctrine driver names (mysqli, pdo_mysql, pdo_pgsql, …) to the
 * driver identifiers `Mindtwo\Monitoring\Support\DatabaseVersion` expects.
 */
final class DatabaseDriver
{
    public static function normalize(string $driver): string
    {
        $driver = strtolower(trim($driver));

        if (str_starts_with($driver, 'pdo_')) {
            $driver = substr($driver, 4);
        }

        return match ($driver) {
            '' => 'unknown',
            'mysqli', 'mysql' => 'mysql',
            'pgsql', 'postgres', 'postgresql' => 'pgsql',
            'sqlite', 'sqlite3' => 'sqlite',
            'sqlsrv', 'mssql' => 'sqlsrv',
            'oci', 'oci8', 'oracle' => 'oracle',
            default => $driver,
        };
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}

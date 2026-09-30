<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Support\DatabaseDriver;

test('typo3 driver names normalize to the core identifiers', function (string $driver, string $expected) {
    expect(DatabaseDriver::normalize($driver))->toBe($expected);
})->with([
    ['mysqli', 'mysql'],
    ['pdo_mysql', 'mysql'],
    ['PDO_MYSQL', 'mysql'],
    ['pdo_pgsql', 'pgsql'],
    ['pdo_sqlite', 'sqlite'],
    ['pdo_sqlsrv', 'sqlsrv'],
    ['oci8', 'oracle'],
    ['', 'unknown'],
    ['custom', 'custom'],
]);

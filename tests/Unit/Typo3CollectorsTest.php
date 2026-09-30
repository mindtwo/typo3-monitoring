<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Collectors\Typo3Collector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3DatabaseCollector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3EnvironmentCollector;
use Mindtwo\Monitoring\Typo3\Collectors\Typo3ExtensionsCollector;

test('the typo3 collector reports the core as a known technology', function () {
    $result = (new Typo3Collector(fakeTypo3()))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe([
            'technology' => 'typo3',
            'version' => '13.4.28',
            'branch' => '13.4',
            'composer_mode' => true,
        ]);
});

test('the typo3 collector is unsupported without a bootstrapped core', function () {
    $typo3 = fakeTypo3();
    $typo3->version = null;

    $collector = new Typo3Collector($typo3);

    expect($collector->supported())->toBeFalse()
        ->and($collector->unsupportedReason())->toContain('TYPO3 version is unavailable');

    $result = $collector->collect();

    expect($result->status)->toBe('unsupported')
        ->and($result->error)->toContain('TYPO3 version is unavailable');
});

test('the extensions collector inventories system and third-party packages', function () {
    $typo3 = fakeTypo3();
    $typo3->extensions = [
        'core' => ['title' => 'TYPO3 CMS Core', 'version' => 'v13.4.28', 'system' => true, 'composer_name' => 'typo3/cms-core'],
        'news' => ['title' => 'News system', 'version' => '13.0.1', 'system' => false, 'composer_name' => 'georgringer/news'],
        'my_sitepackage' => ['title' => '', 'version' => 'dev-main', 'system' => false, 'composer_name' => 'acme/sitepackage'],
    ];

    $result = (new Typo3ExtensionsCollector($typo3))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['count'])->toBe(3)
        ->and($result->data['system_count'])->toBe(1)
        ->and($result->data['third_party_count'])->toBe(2)
        ->and($result->data['extensions'])->toBe([
            ['key' => 'core', 'name' => 'TYPO3 CMS Core', 'version' => '13.4.28', 'type' => 'system'],
            ['key' => 'news', 'name' => 'News system', 'version' => '13.0.1', 'type' => 'third-party'],
            ['key' => 'my_sitepackage', 'name' => 'my_sitepackage', 'version' => 'dev-main', 'type' => 'third-party'],
        ]);
});

test('an empty extension list is still a valid inventory', function () {
    $result = (new Typo3ExtensionsCollector(fakeTypo3()))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['count'])->toBe(0)
        ->and($result->data['extensions'])->toBe([]);
});

test('the database collector normalizes a mariadb connection', function () {
    $typo3 = fakeTypo3();
    $typo3->databaseVersion = ['mysqli', '5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204'];

    $result = (new Typo3DatabaseCollector($typo3))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe([
            'technology' => 'mariadb',
            'version' => '10.11.6',
            'detected_via' => 'connection',
            'driver' => 'mysqli',
        ]);
});

test('the database collector handles the typo3 12 platform-prefixed version string', function () {
    $typo3 = fakeTypo3();
    $typo3->databaseVersion = ['pdo_mysql', 'MySQL 10.11.6-MariaDB-1:10.11.6+maria~ubu2204'];

    $result = (new Typo3DatabaseCollector($typo3))->collect();

    expect($result->data['technology'])->toBe('mariadb')
        ->and($result->data['version'])->toBe('10.11.6')
        ->and($result->data['driver'])->toBe('pdo_mysql');
});

test('the database collector recognizes postgresql', function () {
    $typo3 = fakeTypo3();
    $typo3->databaseVersion = ['pdo_pgsql', 'PostgreSQL 16.2 (Debian 16.2-1.pgdg120+2)'];

    $result = (new Typo3DatabaseCollector($typo3))->collect();

    expect($result->data['technology'])->toBe('postgresql')
        ->and($result->data['version'])->toBe('16.2');
});

test('the database collector fails softly without a connection', function () {
    $typo3 = fakeTypo3();
    $typo3->databaseVersion = null;

    $result = (new Typo3DatabaseCollector($typo3))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('database connection');
});

test('the environment collector reports context, derived name and debug flags', function () {
    $typo3 = fakeTypo3();
    $typo3->applicationContext = 'Production/Staging';
    $typo3->configuration = [
        'SYS/displayErrors' => '1',
        'BE/debug' => true,
        'FE/debug' => false,
    ];

    $result = (new Typo3EnvironmentCollector($typo3))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe([
            'context' => 'Production/Staging',
            'environment' => 'staging',
            'composer_mode' => true,
            'display_errors' => 1,
            'backend_debug' => true,
            'frontend_debug' => false,
        ]);
});

test('the environment collector tolerates missing configuration', function () {
    $typo3 = fakeTypo3();
    $typo3->applicationContext = null;
    $typo3->configuration = [];

    $result = (new Typo3EnvironmentCollector($typo3))->collect();

    expect($result->data['context'])->toBeNull()
        ->and($result->data['environment'])->toBe('production')
        ->and($result->data['display_errors'])->toBeNull()
        ->and($result->data['backend_debug'])->toBeFalse();
});

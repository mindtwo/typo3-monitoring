<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\MonitorFactory;
use Mindtwo\Monitoring\Typo3\MonitorProvider;

test('the monitor combines the base catalog with the typo3 collectors', function () {
    $typo3 = fakeTypo3();
    $typo3->projectPath = sys_get_temp_dir();

    $keys = array_keys(MonitorFactory::make($typo3)->collectors());

    foreach (['os', 'php', 'composer_packages', 'git'] as $baseKey) {
        expect($keys)->toContain($baseKey);
    }

    foreach (['typo3', 'typo3_extensions', 'typo3_environment', 'database'] as $typo3Key) {
        expect($keys)->toContain($typo3Key);
    }
});

test('a snapshot through the factory carries typo3 data and source', function () {
    $typo3 = fakeTypo3();
    $typo3->projectPath = sys_get_temp_dir();
    $typo3->applicationContext = 'Production/Staging';
    $typo3->extensions = [
        'news' => ['title' => 'News system', 'version' => '13.0.1', 'system' => false, 'composer_name' => 'georgringer/news'],
    ];

    $payload = MonitorFactory::make($typo3)->snapshot()->toArray();

    expect($payload['source']['type'])->toBe('typo3')
        ->and($payload['source']['package'])->toBe('mindtwo/typo3-monitoring')
        ->and($payload['project_key'])->toBe('prj_test')
        ->and($payload['environment'])->toBe('staging')
        ->and($payload['metrics']['typo3']['technology'])->toBe('typo3')
        ->and($payload['metrics']['typo3']['version'])->toBe('13.4.28')
        ->and($payload['metrics']['typo3_extensions']['count'])->toBe(1)
        // The live connection replaces the base CLI database detection.
        ->and($payload['metrics']['database']['detected_via'])->toBe('connection')
        ->and($payload['metrics']['database']['technology'])->toBe('mysql')
        ->and($payload['technologies'])->toContain(['technology' => 'typo3', 'version' => '13.4.28', 'source' => 'known']);
});

test('the provider memoises one monitor per process', function () {
    $typo3 = fakeTypo3();
    $typo3->projectPath = sys_get_temp_dir();

    $provider = new MonitorProvider($typo3);

    expect($provider->monitor())->toBe($provider->monitor());
});

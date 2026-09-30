<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Transport\HttpTransport;
use Mindtwo\Monitoring\Typo3\Support\Typo3ConfigurationRepository;
use Mindtwo\Monitoring\Typo3\Tests\Fakes\FakeTypo3Api;

test('credentials are read from the environment', function () {
    $config = new Typo3ConfigurationRepository(fakeTypo3());

    expect($config->credentials()->projectKey)->toBe('prj_test')
        ->and($config->credentials()->secret)->toBe('test-secret')
        ->and($config->credentials()->isComplete())->toBeTrue();
});

test('extension configuration beats the environment', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ENDPOINT'] = 'https://env.example/api/monitoring';
    $typo3->settings['endpoint'] = 'https://settings.example/api/monitoring';

    expect((new Typo3ConfigurationRepository($typo3))->endpoint())
        ->toBe('https://settings.example/api/monitoring');
});

test('a blank extension setting falls through to the environment', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ENDPOINT'] = 'https://env.example/api/monitoring';
    $typo3->settings['endpoint'] = '   ';

    expect((new Typo3ConfigurationRepository($typo3))->endpoint())
        ->toBe('https://env.example/api/monitoring');
});

test('secure defaults apply when nothing is configured', function () {
    $config = new Typo3ConfigurationRepository(new FakeTypo3Api);

    expect($config->credentials()->isComplete())->toBeFalse()
        ->and($config->endpoint())->toBe(HttpTransport::DEFAULT_ENDPOINT)
        ->and($config->ipAllowList())->toBe([])
        ->and($config->enabled())->toBeTrue()
        ->and($config->routeEnabled())->toBeTrue()
        ->and($config->integer('cacheSeconds'))->toBe(300)
        ->and($config->integer('rateLimitPerMinute'))->toBe(10)
        ->and($config->integer('signatureTolerance'))->toBe(300)
        ->and($config->integer('timeout'))->toBe(15)
        ->and($config->environment())->toBe('production');
});

test('extension configuration strings are coerced', function () {
    $typo3 = fakeTypo3();
    $typo3->settings = [
        'routeEnabled' => '0',
        'cacheSeconds' => '120',
        'rateLimitPerMinute' => 'lots',
    ];

    $config = new Typo3ConfigurationRepository($typo3);

    expect($config->routeEnabled())->toBeFalse()
        ->and($config->integer('cacheSeconds'))->toBe(120)
        ->and($config->integer('rateLimitPerMinute'))->toBe(10);
});

test('environment booleans understand false-ish strings', function (string $value) {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ENABLED'] = $value;

    expect((new Typo3ConfigurationRepository($typo3))->enabled())->toBeFalse();
})->with(['0', 'false', 'off', 'no', 'FALSE']);

test('the master switch also disables the pull route', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ENABLED'] = '0';
    $typo3->settings['routeEnabled'] = '1';

    expect((new Typo3ConfigurationRepository($typo3))->routeEnabled())->toBeFalse();
});

test('the ip allow-list is split and trimmed', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_IP_ALLOW_LIST'] = ' 10.0.0.0/8 , 203.0.113.10 ,, ';

    expect((new Typo3ConfigurationRepository($typo3))->ipAllowList())
        ->toBe(['10.0.0.0/8', '203.0.113.10']);
});

test('the environment name is derived from the application context', function () {
    $typo3 = fakeTypo3();
    $typo3->applicationContext = 'Production/Staging';

    expect((new Typo3ConfigurationRepository($typo3))->environment())->toBe('staging');
});

test('the environment name can be overridden', function () {
    $typo3 = fakeTypo3();
    $typo3->applicationContext = 'Production/Staging';
    $typo3->envs['MONITORING_ENVIRONMENT'] = 'qa';

    expect((new Typo3ConfigurationRepository($typo3))->environment())->toBe('qa');

    $typo3->settings['environment'] = 'preview';

    expect((new Typo3ConfigurationRepository($typo3))->environment())->toBe('preview');
});

test('get resolves built-in defaults and falls back to the given default for unknown keys', function () {
    $config = new Typo3ConfigurationRepository(new FakeTypo3Api);

    expect($config->get('timeout'))->toBe(15)
        ->and($config->get('unknown', 42))->toBe(42)
        ->and($config->get('unknown'))->toBeNull();
});

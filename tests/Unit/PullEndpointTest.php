<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Http\PullEndpoint;
use Mindtwo\Monitoring\Typo3\Tests\Fakes\FakeTypo3Api;

function pullEndpoint(?FakeTypo3Api $typo3 = null, ?int &$builds = null): PullEndpoint
{
    $builds = 0;

    return new PullEndpoint($typo3 ?? fakeTypo3(), function () use (&$builds): array {
        $builds++;

        return ['schema_version' => '1.0', 'metrics' => ['build' => $builds]];
    });
}

test('a signed request receives the snapshot', function () {
    [$status, $payload] = pullEndpoint()->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($status)->toBe(200)
        ->and($payload['schema_version'])->toBe('1.0');
});

test('requests for other paths are not handled', function () {
    expect(pullEndpoint()->respond('GET', '/other', '203.0.113.7', signedPullHeaders()))->toBeNull()
        ->and(PullEndpoint::matches('/api/m2-monitoring/'))->toBeTrue()
        ->and(PullEndpoint::matches('/API/M2-MONITORING'))->toBeFalse()
        ->and(PullEndpoint::matches('/api/m2-monitoring/extra'))->toBeFalse();
});

test('only GET is allowed, checked before the disabled-route guard', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ROUTE_ENABLED'] = '0';

    [$status, $payload] = pullEndpoint($typo3)->respond('POST', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($status)->toBe(405)
        ->and($payload)->toBe(['message' => 'Method not allowed.']);
});

test('a disabled route answers 404', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ROUTE_ENABLED'] = 'false';

    [$status] = pullEndpoint($typo3)->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($status)->toBe(404);
});

test('an unconfigured installation answers 503 before verifying anything', function () {
    [$status, $payload] = pullEndpoint(new FakeTypo3Api)->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($status)->toBe(503)
        ->and($payload['message'])->toContain('not configured');
});

test('a bad signature is rejected', function () {
    $headers = signedPullHeaders();
    $headers['X-Monitoring-Signature'] = str_repeat('0', 64);

    [$status] = pullEndpoint()->respond('GET', PullEndpoint::PATH, '203.0.113.7', $headers);

    expect($status)->toBe(401);
});

test('a signature outside the tolerance window is rejected', function () {
    $headers = signedPullHeaders('', time() - 301);

    [$status] = pullEndpoint()->respond('GET', PullEndpoint::PATH, '203.0.113.7', $headers);

    expect($status)->toBe(401);
});

test('the ip allow-list gates the endpoint', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_IP_ALLOW_LIST'] = '10.0.0.0/8';

    [$forbidden] = pullEndpoint($typo3)->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    [$allowed] = pullEndpoint($typo3)->respond('GET', PullEndpoint::PATH, '10.1.2.3', signedPullHeaders());

    expect($forbidden)->toBe(403)
        ->and($allowed)->toBe(200);
});

test('requests are rate limited per ip through the cache', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_RATE_LIMIT'] = '2';
    $typo3->envs['MONITORING_ROUTE_CACHE'] = '0';

    $endpoint = pullEndpoint($typo3);

    [$first] = $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    [$second] = $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    [$third] = $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    [$other] = $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.8', signedPullHeaders());

    expect([$first, $second, $third, $other])->toBe([200, 200, 429, 200]);

    $limiterEntries = array_filter($typo3->cache, static fn (string $key): bool => str_starts_with($key, 'mindtwo-monitoring|pull|'), ARRAY_FILTER_USE_KEY);

    expect($limiterEntries)->not->toBeEmpty();

    foreach ($limiterEntries as [, $ttl]) {
        expect($ttl)->toBe(60);
    }
});

test('the snapshot is cached for the configured seconds', function () {
    $typo3 = fakeTypo3();
    $endpoint = pullEndpoint($typo3, $builds);

    $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($builds)->toBe(1)
        ->and($typo3->cache[PullEndpoint::CACHE_KEY][1])->toBe(300)
        ->and($typo3->cache[PullEndpoint::CACHE_KEY][0]['schema_version'])->toBe('1.0');
});

test('a cache of zero seconds builds every time', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_ROUTE_CACHE'] = '0';
    $endpoint = pullEndpoint($typo3, $builds);

    $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());
    $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($builds)->toBe(2)
        ->and($typo3->cache)->not->toHaveKey(PullEndpoint::CACHE_KEY);
});

test('a failing snapshot builder answers 500 without leaking the error', function () {
    $endpoint = new PullEndpoint(fakeTypo3(), static function (): array {
        throw new RuntimeException('database exploded');
    });

    [$status, $payload] = $endpoint->respond('GET', PullEndpoint::PATH, '203.0.113.7', signedPullHeaders());

    expect($status)->toBe(500)
        ->and($payload['message'])->not->toContain('exploded');
});

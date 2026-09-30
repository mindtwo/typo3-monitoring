<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Middleware\PullMiddleware;
use Mindtwo\Monitoring\Typo3\MonitorProvider;
use Mindtwo\Monitoring\Typo3\Tests\Fakes\FakeTypo3Api;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The next handler in the stack: answers with a recognisable 418 so a test can
 * tell "passed through" from "handled by the middleware".
 */
function passThroughHandler(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface
    {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response('php://temp', 418);
        }
    };
}

/**
 * @param  array<string, string>  $headers
 */
function pullRequest(string $path, string $method = 'GET', array $headers = [], string $remoteAddr = '203.0.113.7'): ServerRequest
{
    return new ServerRequest('https://example.test'.$path, $method, 'php://temp', $headers, ['REMOTE_ADDR' => $remoteAddr]);
}

function pullMiddleware(?FakeTypo3Api $typo3 = null): PullMiddleware
{
    $typo3 ??= fakeTypo3();

    return new PullMiddleware($typo3, new MonitorProvider($typo3));
}

test('requests for other paths pass through untouched', function () {
    $response = pullMiddleware()->process(pullRequest('/some/page'), passThroughHandler());

    expect($response->getStatusCode())->toBe(418);
});

test('an unsigned request on the endpoint is answered by the middleware with 401 json', function () {
    $response = pullMiddleware()->process(pullRequest('/api/m2-monitoring'), passThroughHandler());

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getHeaderLine('Content-Type'))->toContain('application/json')
        ->and($response->getHeaderLine('Cache-Control'))->toBe('no-store, private')
        ->and(json_decode((string) $response->getBody(), true))->toBe(['message' => 'Unauthorized.']);
});

test('a non-GET request on the endpoint is rejected with 405', function () {
    $response = pullMiddleware()->process(pullRequest('/api/m2-monitoring', 'POST'), passThroughHandler());

    expect($response->getStatusCode())->toBe(405);
});

test('the ip allow-list is checked against REMOTE_ADDR without normalized params', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_IP_ALLOW_LIST'] = '10.0.0.0/8';

    $response = pullMiddleware($typo3)->process(
        pullRequest('/api/m2-monitoring', 'GET', signedPullHeaders(), '203.0.113.7'),
        passThroughHandler()
    );

    expect($response->getStatusCode())->toBe(403);
});

test('normalized params win over REMOTE_ADDR when present', function () {
    $typo3 = fakeTypo3();
    $typo3->envs['MONITORING_IP_ALLOW_LIST'] = '10.0.0.0/8';

    // The raw request says 203.0.113.7, TYPO3's normalized view (reverse-proxy aware) says 10.0.0.9.
    $request = pullRequest('/api/m2-monitoring', 'GET', signedPullHeaders(), '203.0.113.7');
    // The constructor is used directly: the static factories need a bootstrapped TYPO3 Environment.
    $normalized = new NormalizedParams(
        ['REMOTE_ADDR' => '10.0.0.9', 'HTTP_HOST' => 'example.test', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/var/www/public/index.php'],
        [],
        '/var/www/public/index.php',
        '/var/www/public/'
    );

    $response = pullMiddleware($typo3)->process($request->withAttribute('normalizedParams', $normalized), passThroughHandler());

    // Allowed by the allow-list means the request reached the signature check and beyond (not 403).
    expect($response->getStatusCode())->not->toBe(403);
});

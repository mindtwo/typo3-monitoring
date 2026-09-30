<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Http;

use Mindtwo\Monitoring\Http\PullRequestHandler;
use Mindtwo\Monitoring\Support\FixedWindowRateLimiter;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;
use Mindtwo\Monitoring\Typo3\Support\Typo3ConfigurationRepository;

/**
 * Pure request logic of the GET /api/m2-monitoring endpoint: path and method
 * matching, the disabled-route guard, then rate limiting, IP allow-list and
 * signature verification through the shared PullRequestHandler, with
 * short-lived snapshot caching. The PSR-15 middleware only maps the request in
 * and the result out.
 */
final class PullEndpoint
{
    public const PATH = '/api/m2-monitoring';

    public const CACHE_KEY = 'mindtwo-monitoring.snapshot';

    private Typo3ConfigurationRepository $config;

    /** @var callable(): array<string, mixed> */
    private $snapshot;

    /**
     * @param  callable(): array<string, mixed>  $snapshot  builds a fresh snapshot payload
     */
    public function __construct(
        private Typo3Api $typo3,
        callable $snapshot,
        ?Typo3ConfigurationRepository $config = null
    ) {
        $this->config = $config ?? new Typo3ConfigurationRepository($typo3);
        $this->snapshot = $snapshot;
    }

    /**
     * Whether a request path addresses this endpoint (exact, case-sensitive,
     * a trailing slash tolerated).
     */
    public static function matches(string $path): bool
    {
        return rtrim($path, '/') === self::PATH;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: int, 1: array<string, mixed>}|null [HTTP status code, JSON payload], or null when the request is not for this endpoint
     */
    public function respond(string $method, string $path, string $ip, array $headers, string $body = ''): ?array
    {
        if (! self::matches($path)) {
            return null;
        }

        if (strtoupper($method) !== 'GET') {
            return [405, ['message' => 'Method not allowed.']];
        }

        if (! $this->config->routeEnabled()) {
            return [404, ['message' => 'Not found.']];
        }

        $handler = new PullRequestHandler(
            $this->config,
            new HmacSignatureVerifier(max(0, $this->config->integer('signatureTolerance'))),
            $this->rateLimiter()
        );

        return $handler->handle($ip, $headers, $body, function (): array {
            return $this->cachedSnapshot();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedSnapshot(): array
    {
        $seconds = max(0, $this->config->integer('cacheSeconds'));

        if ($seconds > 0) {
            $cached = $this->typo3->cacheGet(self::CACHE_KEY);

            if (is_array($cached) && $cached !== []) {
                /** @var array<string, mixed> $cached */
                return $cached;
            }
        }

        $payload = ($this->snapshot)();

        if ($seconds > 0) {
            $this->typo3->cacheSet(self::CACHE_KEY, $payload, $seconds);
        }

        return $payload;
    }

    private function rateLimiter(): FixedWindowRateLimiter
    {
        return new FixedWindowRateLimiter(
            fn (string $key) => $this->typo3->cacheGet($key),
            function (string $key, $value, int $ttl): void {
                $this->typo3->cacheSet($key, $value, $ttl);
            },
            max(1, $this->config->integer('rateLimitPerMinute')),
            60
        );
    }
}

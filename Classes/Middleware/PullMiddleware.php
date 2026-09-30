<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Middleware;

use Mindtwo\Monitoring\Typo3\Http\PullEndpoint;
use Mindtwo\Monitoring\Typo3\MonitorProvider;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * PSR-15 glue for GET /api/m2-monitoring. Registered ahead of site resolution
 * (Configuration/RequestMiddlewares.php), so it answers on every host, without
 * a matching site and even in maintenance mode. All decisions live in
 * PullEndpoint; this class only maps the PSR-7 request in and the result out.
 */
final class PullMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Typo3Api $typo3,
        private MonitorProvider $monitors,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Cheap early exit for normal traffic: no configuration is read.
        if (! PullEndpoint::matches($path)) {
            return $handler->handle($request);
        }

        $endpoint = new PullEndpoint(
            $this->typo3,
            fn (): array => $this->monitors->monitor()->snapshot()->toArray()
        );

        $result = $endpoint->respond(
            $request->getMethod(),
            $path,
            $this->clientIp($request),
            $this->headers($request),
            (string) $request->getBody()
        );

        if ($result === null) {
            return $handler->handle($request);
        }

        [$status, $payload] = $result;

        return new JsonResponse(
            $payload,
            $status,
            ['Cache-Control' => 'no-store, private'],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * The client address as TYPO3 sees it — reverse-proxy aware through
     * SYS/reverseProxyIP when the normalized params are available.
     */
    private function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getAttribute('normalizedParams');

        if ($params instanceof NormalizedParams) {
            return $params->getRemoteAddress();
        }

        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($remote) ? $remote : '';
    }

    /**
     * First value per header name; the verifier matches names case-insensitively.
     *
     * @return array<string, string>
     */
    private function headers(ServerRequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $first = $values[0] ?? null;

            if (is_string($first)) {
                $headers[(string) $name] = $first;
            }
        }

        return $headers;
    }
}

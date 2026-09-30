<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Transport\HttpTransport;

/**
 * Configuration with the suite's priority chain: extension configuration
 * (blank counts as unset) → MONITORING_* environment variables → secure
 * defaults. The project key and the secret are read from the environment by
 * design — they are deliberately absent from ext_conf_template.txt, because
 * settings.php is versioned and deployed to every stage.
 */
final class Typo3ConfigurationRepository implements ConfigurationRepository
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'enabled' => true,
        'projectKey' => '',
        'secret' => '',
        'endpoint' => HttpTransport::DEFAULT_ENDPOINT,
        'environment' => '',
        'ipAllowList' => '',
        'routeEnabled' => true,
        'cacheSeconds' => 300,
        'rateLimitPerMinute' => 10,
        'signatureTolerance' => 300,
        'timeout' => 15,
    ];

    /** @var array<string, string> setting key => environment variable */
    private const ENVIRONMENT_NAMES = [
        'enabled' => 'MONITORING_ENABLED',
        'projectKey' => 'MONITORING_PROJECT_KEY',
        'secret' => 'MONITORING_SECRET',
        'endpoint' => 'MONITORING_ENDPOINT',
        'environment' => 'MONITORING_ENVIRONMENT',
        'ipAllowList' => 'MONITORING_IP_ALLOW_LIST',
        'routeEnabled' => 'MONITORING_ROUTE_ENABLED',
        'cacheSeconds' => 'MONITORING_ROUTE_CACHE',
        'rateLimitPerMinute' => 'MONITORING_RATE_LIMIT',
        'signatureTolerance' => 'MONITORING_SIGNATURE_TOLERANCE',
        'timeout' => 'MONITORING_TIMEOUT',
    ];

    public function __construct(private Typo3Api $typo3) {}

    public function credentials(): Credentials
    {
        return new Credentials(
            trim((string) $this->value('projectKey')),
            trim((string) $this->value('secret'))
        );
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->value('endpoint'));

        return $endpoint !== '' ? $endpoint : HttpTransport::DEFAULT_ENDPOINT;
    }

    /**
     * @return array<int, string>
     */
    public function ipAllowList(): array
    {
        $list = $this->value('ipAllowList');

        if (is_string($list)) {
            $list = explode(',', $list);
        }

        if (! is_array($list)) {
            return [];
        }

        $entries = [];

        foreach ($list as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $entries[] = trim($entry);
            }
        }

        return $entries;
    }

    /**
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $value = $this->value($key);

        return $value !== null && $value !== '' ? $value : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function routeEnabled(): bool
    {
        return $this->enabled() && $this->boolean('routeEnabled');
    }

    public function integer(string $key): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : (int) (self::DEFAULTS[$key] ?? 0);
    }

    /**
     * The snapshot's environment name: an explicit override, otherwise derived
     * from the TYPO3 application context.
     */
    public function environment(): string
    {
        $override = trim((string) $this->value('environment'));

        return $override !== '' ? $override : EnvironmentName::fromContext($this->typo3->applicationContext());
    }

    /**
     * Extension configuration first (blank counts as unset — ext conf values
     * are always strings), then the environment variable, then the default.
     */
    private function value(string $key): mixed
    {
        $settings = $this->typo3->settings();

        if (isset($settings[$key]) && is_scalar($settings[$key]) && trim((string) $settings[$key]) !== '') {
            return $settings[$key];
        }

        $environmentName = self::ENVIRONMENT_NAMES[$key] ?? null;

        if ($environmentName !== null) {
            $env = $this->typo3->env($environmentName);

            if ($env !== null && $env !== '') {
                return $env;
            }
        }

        return self::DEFAULTS[$key] ?? null;
    }

    private function boolean(string $key): bool
    {
        $value = $this->value($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['0', 'false', 'off', 'no', ''], true);
        }

        return (bool) $value;
    }
}

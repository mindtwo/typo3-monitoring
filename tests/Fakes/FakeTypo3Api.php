<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Tests\Fakes;

use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * Array-backed test double: every method returns the matching public property.
 * Cache keys are stored raw — hashing them into valid TYPO3 identifiers is a
 * concern of the native implementation, not of the callers.
 */
final class FakeTypo3Api implements Typo3Api
{
    public ?string $version = '13.4.28';

    /** @var array<string, array{title: ?string, version: ?string, system: bool, composer_name: ?string}> */
    public array $extensions = [];

    /** @var array{0: string, 1: string}|null */
    public ?array $databaseVersion = ['mysqli', '8.0.36'];

    public ?string $applicationContext = 'Production';

    public bool $composerMode = true;

    /** @var array<string, mixed> keyed by slash path */
    public array $configuration = [
        'SYS/displayErrors' => -1,
        'BE/debug' => false,
        'FE/debug' => false,
        'DB/Connections/Default/driver' => 'mysqli',
    ];

    public ?string $projectPath = null;

    /** @var array<string, string> */
    public array $envs = [];

    /** @var array<string, mixed> */
    public array $settings = [];

    /** @var array<string, array{0: mixed, 1: int}> */
    public array $cache = [];

    public function version(): ?string
    {
        return $this->version;
    }

    public function extensions(): array
    {
        return $this->extensions;
    }

    public function databaseVersion(): ?array
    {
        return $this->databaseVersion;
    }

    public function applicationContext(): ?string
    {
        return $this->applicationContext;
    }

    public function isComposerMode(): bool
    {
        return $this->composerMode;
    }

    public function configuration(string $path): mixed
    {
        return $this->configuration[$path] ?? null;
    }

    public function projectPath(): ?string
    {
        return $this->projectPath;
    }

    public function env(string $name): ?string
    {
        return $this->envs[$name] ?? null;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function cacheGet(string $key): mixed
    {
        return $this->cache[$key][0] ?? null;
    }

    public function cacheSet(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->cache[$key] = [$value, $ttlSeconds];
    }
}

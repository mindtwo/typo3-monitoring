<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

/**
 * The thin seam between this extension and the TYPO3 core. Everything the
 * collectors, the configuration and the pull endpoint need from TYPO3 goes
 * through this interface, so all of it is unit-testable against a fake — no
 * TYPO3 bootstrap required.
 */
interface Typo3Api
{
    /**
     * The TYPO3 core version, e.g. "13.4.28"; null when TYPO3 is not bootstrapped.
     */
    public function version(): ?string;

    /**
     * Active packages keyed by extension key. `version` is the raw value TYPO3
     * reports (a composer pretty version such as "v13.4.28" or "dev-main");
     * `system` is true for core framework packages.
     *
     * @return array<string, array{title: ?string, version: ?string, system: bool, composer_name: ?string}>
     */
    public function extensions(): array;

    /**
     * @return array{0: string, 1: string}|null [TYPO3 driver name e.g. "mysqli", raw server version]
     */
    public function databaseVersion(): ?array;

    /**
     * The full application context string, e.g. "Production/Staging"; null when unavailable.
     */
    public function applicationContext(): ?string;

    public function isComposerMode(): bool;

    /**
     * The value at a slash-separated path of $GLOBALS['TYPO3_CONF_VARS']
     * (e.g. "SYS/displayErrors"), or null when the path does not exist.
     */
    public function configuration(string $path): mixed;

    /**
     * The project root (the directory containing composer.json), or null.
     */
    public function projectPath(): ?string;

    public function env(string $name): ?string;

    /**
     * The extension configuration of mindtwo_monitoring as stored (string
     * values), or [] when the extension is not configured.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;

    public function cacheGet(string $key): mixed;

    public function cacheSet(string $key, mixed $value, int $ttlSeconds): void;
}

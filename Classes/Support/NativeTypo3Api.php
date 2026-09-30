<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

use Composer\InstalledVersions;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * The real TYPO3 adapter. Every call is guarded: monitoring must never break
 * the host installation, so an unavailable service degrades to null/[] and
 * the collector reports the gap instead of throwing.
 *
 * Built by the container (constructor injection) — every consumer is itself a
 * container service (commands, middleware), so GeneralUtility::makeInstance()
 * is never needed.
 */
final class NativeTypo3Api implements Typo3Api
{
    public const EXTENSION_KEY = 'mindtwo_monitoring';

    public const CACHE_IDENTIFIER = 'mindtwo_monitoring';

    private const SYSTEM_PACKAGE_TYPE = 'typo3-cms-framework';

    private ?FrontendInterface $cache = null;

    private bool $cacheResolved = false;

    public function __construct(
        private Typo3Version $typo3Version,
        private PackageManager $packages,
        private ConnectionPool $connections,
        private CacheManager $caches,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function version(): ?string
    {
        try {
            $version = $this->typo3Version->getVersion();

            return $version !== '' ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function extensions(): array
    {
        try {
            $packages = $this->packages->getActivePackages();
        } catch (Throwable) {
            return [];
        }

        $extensions = [];

        foreach ($packages as $package) {
            if (! $package instanceof PackageInterface) {
                continue;
            }

            try {
                $extensions[$package->getPackageKey()] = $this->describe($package);
            } catch (Throwable) {
                // One broken package must not hide the rest of the inventory.
            }
        }

        return $extensions;
    }

    public function databaseVersion(): ?array
    {
        try {
            $connection = $this->connections->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
            $driver = $this->configuration('DB/Connections/Default/driver');

            return [
                is_string($driver) && $driver !== '' ? $driver : 'unknown',
                $connection->getServerVersion(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function applicationContext(): ?string
    {
        try {
            $context = (string) Environment::getContext();

            return $context !== '' ? $context : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function isComposerMode(): bool
    {
        try {
            return Environment::isComposerMode();
        } catch (Throwable) {
            return false;
        }
    }

    public function configuration(string $path): mixed
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        if (! is_array($configuration)) {
            return null;
        }

        try {
            return ArrayUtility::getValueByPath($configuration, $path);
        } catch (Throwable) {
            return null;
        }
    }

    public function projectPath(): ?string
    {
        try {
            $path = Environment::getProjectPath();

            return $path !== '' ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function env(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        if ($value === null || $value === false) {
            return null;
        }

        if ($value === true) {
            return '1';
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public function settings(): array
    {
        try {
            $settings = $this->extensionConfiguration->get(self::EXTENSION_KEY);
        } catch (Throwable) {
            // Not configured yet (extension freshly installed) or the
            // configuration is unavailable in this phase — fall back to env.
            return [];
        }

        return is_array($settings) ? $settings : [];
    }

    public function cacheGet(string $key): mixed
    {
        try {
            $value = $this->cache()?->get(self::identifier($key));
        } catch (Throwable) {
            return null;
        }

        return $value === false ? null : $value;
    }

    public function cacheSet(string $key, mixed $value, int $ttlSeconds): void
    {
        try {
            // A lifetime of 0 means "unlimited" to TYPO3's backends.
            $this->cache()?->set(self::identifier($key), $value, [], max(1, $ttlSeconds));
        } catch (Throwable) {
            // Best effort: a cache failure only costs a rebuild or a missed throttle window.
        }
    }

    /**
     * @return array{title: ?string, version: ?string, system: bool, composer_name: ?string}
     */
    private function describe(PackageInterface $package): array
    {
        $metaData = $package->getPackageMetaData();
        $title = $metaData->getTitle();
        $version = (string) $metaData->getVersion();
        $composerName = $this->composerName($package);

        if ($version === '' && $composerName !== null) {
            $version = $this->installedVersion($composerName) ?? '';
        }

        return [
            'title' => $title !== null && $title !== '' ? $title : null,
            'version' => $version !== '' ? $version : null,
            'system' => (string) $metaData->getPackageType() === self::SYSTEM_PACKAGE_TYPE,
            'composer_name' => $composerName,
        ];
    }

    private function composerName(PackageInterface $package): ?string
    {
        if (! $package instanceof Package) {
            return null;
        }

        try {
            $name = $package->getValueFromComposerManifest('name');
        } catch (Throwable) {
            return null;
        }

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function installedVersion(string $composerName): ?string
    {
        try {
            if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($composerName)) {
                return null;
            }

            return InstalledVersions::getPrettyVersion($composerName);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolved lazily: the CacheManager must not be used while ext_localconf
     * files are still being loaded, and a missing cache registration must
     * degrade to "no cache", not to an exception.
     */
    private function cache(): ?FrontendInterface
    {
        if ($this->cacheResolved) {
            return $this->cache;
        }

        $this->cacheResolved = true;

        try {
            $this->cache = $this->caches->getCache(self::CACHE_IDENTIFIER);
        } catch (Throwable) {
            $this->cache = null;
        }

        return $this->cache;
    }

    /**
     * TYPO3 cache identifiers must match [a-zA-Z0-9_%-&]{1,250}; the shared
     * limiter keys contain "|" and ".", so every key is hashed.
     */
    private static function identifier(string $key): string
    {
        return 'm2mon_'.hash('sha256', $key);
    }
}

<?php

declare(strict_types=1);

defined('TYPO3') || exit();

/*
 * Throttle counters and the cached pull snapshot. FileBackend: TTL-capable and
 * without a database table, so no `extension:setup` is required. No group, so
 * `cache:flush --group …` never resets the limiter; entries are recomputable,
 * so a plain `cache:flush` is harmless. `??=` lets additional.php swap the
 * backend (e.g. RedisBackend on multi-node hosting).
 */
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['mindtwo_monitoring'] ??= [
    'frontend' => TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
    'options' => ['defaultLifetime' => 300],
    'groups' => [],
];

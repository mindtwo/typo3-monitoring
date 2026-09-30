<?php

declare(strict_types=1);

/*
 * The signed pull endpoint runs after the client IP has been normalised
 * (reverse-proxy aware) and before site resolution: it answers on any host,
 * without a matching site configuration and even in maintenance mode.
 */
return [
    'frontend' => [
        'mindtwo/monitoring/pull' => [
            'target' => Mindtwo\Monitoring\Typo3\Middleware\PullMiddleware::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];

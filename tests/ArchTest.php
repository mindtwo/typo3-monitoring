<?php

declare(strict_types=1);

test('all source files declare strict types')
    ->expect('Mindtwo\Monitoring\Typo3')
    ->toUseStrictTypes();

test('no debug or dangerous shell helpers are used')
    ->expect('Mindtwo\Monitoring\Typo3')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'eval']);

test('the extension never couples to laravel, wordpress or craft')
    ->expect('Mindtwo\Monitoring\Typo3')
    ->not->toUse(['Illuminate', 'Laravel', 'Mindtwo\Monitoring\WordPress', 'Mindtwo\Monitoring\Craft']);

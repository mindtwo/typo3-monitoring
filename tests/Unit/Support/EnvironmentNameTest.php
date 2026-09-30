<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Support\EnvironmentName;

test('root contexts map to their lowercase name', function (string $context, string $expected) {
    expect(EnvironmentName::fromContext($context))->toBe($expected);
})->with([
    ['Production', 'production'],
    ['Development', 'development'],
    ['Testing', 'testing'],
    ['Development/Local', 'development'],
    ['Production/Live', 'production'],
]);

test('a staging sub-context of production maps to staging', function (string $context) {
    expect(EnvironmentName::fromContext($context))->toBe('staging');
})->with(['Production/Staging', 'Production/Stage', 'production/staging/blue']);

test('unknown or missing contexts fall back to production', function (?string $context) {
    expect(EnvironmentName::fromContext($context))->toBe('production');
})->with([null, '', '   ', 'Weird', 'Staging']);

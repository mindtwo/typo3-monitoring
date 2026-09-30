<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Typo3\Support\ExtensionVersion;

test('composer pretty versions are normalized', function (?string $raw, ?string $expected) {
    expect(ExtensionVersion::normalize($raw))->toBe($expected);
})->with([
    ['v13.4.28', '13.4.28'],
    ['V1.0', '1.0'],
    ['13.0.1', '13.0.1'],
    ['dev-main', 'dev-main'],
    ['vendor-branch', 'vendor-branch'],
    [' 1.2.3 ', '1.2.3'],
    ['', null],
    ['   ', null],
    [null, null],
]);

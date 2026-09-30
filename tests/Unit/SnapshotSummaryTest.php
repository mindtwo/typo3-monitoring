<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\Typo3\Support\SnapshotSummary;

function summarySnapshot(): Snapshot
{
    return new Snapshot(
        Snapshot::SCHEMA_VERSION,
        '2026-01-01T00:00:00+00:00',
        'production',
        Source::plugin(Source::TYPE_TYPO3, 'mindtwo/typo3-monitoring')
    );
}

test('rows render one metric per result in snapshot order', function () {
    $snapshot = summarySnapshot()
        ->add(CollectionResult::ok('typo3', ['technology' => 'typo3', 'version' => '13.4.28']))
        ->add(CollectionResult::failed('database', 'Unable to inspect the TYPO3 database connection.'));

    $rows = SnapshotSummary::rows($snapshot);

    expect($rows)->toBe([
        ['typo3', 'ok', 'typo3 13.4.28'],
        ['database', 'failed', 'Unable to inspect the TYPO3 database connection.'],
    ]);
});

test('technology results summarize as name and version', function () {
    $result = CollectionResult::ok('php', ['technology' => 'php', 'version' => '8.3.14']);

    expect(SnapshotSummary::summarize($result))->toBe('php 8.3.14');
});

test('a technology without a version omits it', function () {
    $result = CollectionResult::ok('redis', ['technology' => 'redis']);

    expect(SnapshotSummary::summarize($result))->toBe('redis');
});

test('counted results summarize as an entry count', function () {
    $result = CollectionResult::ok('typo3_extensions', ['count' => 12, 'extensions' => []]);

    expect(SnapshotSummary::summarize($result))->toBe('12 entries');
});

test('errors win over data and are truncated', function () {
    $long = str_repeat('x', 80);
    $result = CollectionResult::failed('git', $long);

    expect(SnapshotSummary::summarize($result))
        ->toBe(str_repeat('x', 70).'…');
});

test('empty data summarizes as an empty string', function () {
    expect(SnapshotSummary::summarize(CollectionResult::ok('os')))->toBe('');
});

test('arbitrary data falls back to compact json', function () {
    $result = CollectionResult::ok('typo3_environment', ['composer_mode' => true]);

    expect(SnapshotSummary::summarize($result))->toBe('{"composer_mode":true}');
});

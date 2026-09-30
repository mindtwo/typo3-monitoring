<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Support;

use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\Snapshot;

/**
 * Plain-text presentation of a snapshot for the console `show` command —
 * kept out of the Symfony command so it stays unit-testable.
 */
final class SnapshotSummary
{
    /**
     * One [metric, status, details] row per collection result.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function rows(Snapshot $snapshot): array
    {
        $rows = [];

        foreach ($snapshot->results() as $key => $result) {
            $rows[] = [(string) $key, $result->status, self::summarize($result)];
        }

        return $rows;
    }

    public static function summarize(CollectionResult $result): string
    {
        if ($result->error !== null) {
            return self::truncate($result->error);
        }

        if (isset($result->data['technology'])) {
            $version = $result->data['version'] ?? null;

            return trim((string) $result->data['technology'].' '.(is_scalar($version) ? (string) $version : ''));
        }

        if (isset($result->data['count'])) {
            return $result->data['count'].' entries';
        }

        if ($result->data === []) {
            return '';
        }

        return self::truncate((string) json_encode($result->data, JSON_UNESCAPED_SLASHES));
    }

    private static function truncate(string $value): string
    {
        return mb_strlen($value) > 70 ? mb_substr($value, 0, 70).'…' : $value;
    }

    private function __construct()
    {
        // Static helper — never instantiated.
    }
}

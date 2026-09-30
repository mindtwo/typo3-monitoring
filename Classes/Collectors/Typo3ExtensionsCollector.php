<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Collectors;

use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Typo3\Support\ExtensionVersion;
use Mindtwo\Monitoring\Typo3\Support\Typo3Api;

/**
 * Raw inventory of the active extensions with their versions, split into
 * system (core framework) and third-party packages.
 */
final class Typo3ExtensionsCollector extends AbstractCollector
{
    public const TYPE_SYSTEM = 'system';

    public const TYPE_THIRD_PARTY = 'third-party';

    public function __construct(private Typo3Api $typo3) {}

    public function key(): string
    {
        return 'typo3_extensions';
    }

    public function collect(): CollectionResult
    {
        $extensions = [];
        $systemCount = 0;

        foreach ($this->typo3->extensions() as $key => $info) {
            $system = (bool) ($info['system'] ?? false);
            $title = isset($info['title']) && is_string($info['title']) && trim($info['title']) !== ''
                ? trim($info['title'])
                : (string) $key;

            if ($system) {
                $systemCount++;
            }

            $extensions[] = [
                'key' => (string) $key,
                'name' => $title,
                'version' => ExtensionVersion::normalize(isset($info['version']) && is_string($info['version']) ? $info['version'] : null),
                'type' => $system ? self::TYPE_SYSTEM : self::TYPE_THIRD_PARTY,
            ];
        }

        return CollectionResult::ok($this->key(), [
            'count' => count($extensions),
            'system_count' => $systemCount,
            'third_party_count' => count($extensions) - $systemCount,
            'extensions' => $extensions,
        ]);
    }
}

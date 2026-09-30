<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Mindtwo\Monitoring\Typo3\Tests\Fakes\FakeTypo3Api;

/**
 * A TYPO3 whose credentials come from the environment — the documented source
 * for the project key and the secret.
 */
function fakeTypo3(): FakeTypo3Api
{
    $typo3 = new FakeTypo3Api;
    $typo3->envs = [
        'MONITORING_PROJECT_KEY' => 'prj_test',
        'MONITORING_SECRET' => 'test-secret',
    ];

    return $typo3;
}

/**
 * @return array<string, string>
 */
function signedPullHeaders(string $body = '', ?int $timestamp = null): array
{
    $signer = new HmacRequestSigner($timestamp !== null ? static fn (): int => $timestamp : null);

    return $signer->headers($body, new Credentials('prj_test', 'test-secret'));
}

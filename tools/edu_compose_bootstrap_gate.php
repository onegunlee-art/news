<?php
/**
 * EDU compose.php bootstrap 배포 게이트 — 공유 판정 (deploy verify / self-test)
 */
declare(strict_types=1);

/**
 * @param array{http: int, raw: string} $probe
 */
function eduComposeBootstrapGateError(array $probe): ?string
{
    $http = (int) ($probe['http'] ?? 0);
    $body = trim((string) ($probe['raw'] ?? ''));

    if ($http === 500 && $body === '') {
        return 'compose.php bootstrap fatal (HTTP 500 empty body — eduAgents.php require missing?)';
    }

    if ($http !== 401) {
        return "compose.php without token should return 401 JSON, got HTTP {$http}";
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return 'compose.php 401 response is not JSON';
    }

    $error = (string) ($data['error'] ?? '');
    if ($error !== 'X-Edu-Token required') {
        return 'compose.php 401 JSON missing X-Edu-Token required error';
    }

    return null;
}

/**
 * @param array{http: int, raw: string} $probe
 */
function eduComposeBootstrapGatePass(array $probe): bool
{
    return eduComposeBootstrapGateError($probe) === null;
}

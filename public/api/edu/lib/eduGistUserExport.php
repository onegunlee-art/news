<?php
/**
 * GIST EDU — GIST users export helpers (READ ONLY MySQL users)
 */
declare(strict_types=1);

const EDU_GIST_EXPORT_TEST_EMAILS = [
    'test@test.com',
    'test@hyundai.com',
    'onegunlee@gmail.com',
];

/** @param list<string> $corporateEmails */
function eduGistExportIsExcluded(array $user, array $corporateEmails): bool
{
    $role = (string) ($user['role'] ?? '');
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $nickname = trim((string) ($user['nickname'] ?? ''));
    $companyTag = strtolower(trim((string) ($user['company_tag'] ?? '')));

    if ($role === 'admin') {
        return true;
    }
    if ($companyTag === 'hyundai') {
        return true;
    }
    if ($email !== '' && str_ends_with($email, '@hyundai.com')) {
        return true;
    }
    if ($email !== '' && in_array($email, EDU_GIST_EXPORT_TEST_EMAILS, true)) {
        return true;
    }
    if ($email !== '' && in_array($email, $corporateEmails, true)) {
        return true;
    }
    if ($nickname === '관리자' || str_contains($nickname, '관리자')) {
        return true;
    }

    return false;
}

/** @return list<array<string, mixed>> */
function eduGistExportFromPdo(PDO $pdo): array
{
    $corporateEmails = [];
    try {
        $stmt = $pdo->query('SELECT LOWER(email) AS email FROM corporate_otp_skip');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $corporateEmails[] = (string) $row['email'];
        }
    } catch (Throwable $e) {
        // table may not exist in some envs
    }

    $stmt = $pdo->query("
        SELECT id, nickname, email, kakao_id, profile_image, role, company_tag, status, created_at
        FROM users
        WHERE status = 'active'
        ORDER BY id ASC
    ");

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!eduGistExportIsExcluded($row, $corporateEmails)) {
            $out[] = $row;
        }
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function eduGistExportLoadJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("gist json not found: {$path}");
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        throw new RuntimeException('invalid gist json');
    }
    $users = $raw['users'] ?? $raw;
    if (!is_array($users)) {
        throw new RuntimeException('invalid gist json users');
    }

    return array_values(array_filter($users, static fn ($u): bool => is_array($u)));
}

/** @return list<array<string, mixed>> */
function eduGistExportFetchUrl(string $url, string $adminKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'X-Edu-Admin-Key: ' . $adminKey,
            'Accept: application/json',
        ],
    ]);
    if (getenv('PHP_CURL_SSL_NO_VERIFY') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        throw new RuntimeException("gist fetch HTTP {$code}");
    }
    $raw = json_decode((string) $body, true);
    if (!is_array($raw) || empty($raw['users'])) {
        throw new RuntimeException('gist fetch invalid payload');
    }

    return $raw['users'];
}

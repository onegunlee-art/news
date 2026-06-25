<?php
/**
 * 기업 고객 일괄 등록 CLI (Admin UI와 동일 DB 작업)
 *
 * 사용:
 *   php tools/corporate_batch_seed.php --email=test@hyundai.com --dry-run
 *   php tools/corporate_batch_seed.php --email=test@hyundai.com --apply
 *   php tools/corporate_batch_seed.php --file=docs/corporate/hyundai_emails.txt --apply --no-mail
 *
 * 사전: database/migrations/add_corporate_users.sql 실행
 */

declare(strict_types=1);

$options = getopt('', ['email:', 'file:', 'password::', 'company::', 'months::', 'apply', 'dry-run', 'no-mail']);

$password = $options['password'] ?? 'gist2026';
$companyTag = strtolower($options['company'] ?? 'hyundai');
$months = (int) ($options['months'] ?? 12);
$sendMail = !isset($options['no-mail']);

$emails = [];
if (!empty($options['email'])) {
    $emails[] = strtolower(trim((string) $options['email']));
}
if (!empty($options['file'])) {
    $path = (string) $options['file'];
    if (!is_file($path)) {
        fwrite(STDERR, "File not found: {$path}\n");
        exit(1);
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $emails[] = strtolower($line);
    }
}

$emails = array_values(array_unique(array_filter($emails, static fn (string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)));

if ($emails === []) {
    fwrite(STDERR, "Usage: php tools/corporate_batch_seed.php --email=... [--apply] OR --file=...\n");
    exit(1);
}

if (strlen($password) < 6) {
    fwrite(STDERR, "Password must be at least 6 characters.\n");
    exit(1);
}

$dryRun = isset($options['dry-run']) || !isset($options['apply']);

echo ($dryRun ? '[DRY-RUN] ' : '[APPLY] ') . count($emails) . " email(s), company={$companyTag}, months={$months}\n";

if ($dryRun) {
    foreach ($emails as $email) {
        echo "  - {$email}\n";
    }
    echo "Run with --apply to execute.\n";
    exit(0);
}

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || ($line[0] ?? '') === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$n, $v] = explode('=', $line, 2);
            $n = trim($n);
            $v = trim($v, " \t\"'");
            if ($n !== '') {
                putenv("{$n}={$v}");
                $_ENV[$n] = $v;
            }
        }
    }
}

$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];

$configPath = $root . '/config/database.php';
if (is_file($configPath)) {
    $cfg = require $configPath;
    if (is_array($cfg)) {
        $dbConfig['host'] = $cfg['host'] ?? $dbConfig['host'];
        $dbConfig['database'] = $cfg['database'] ?? $cfg['dbname'] ?? $dbConfig['database'];
        $dbConfig['username'] = $cfg['username'] ?? $dbConfig['username'];
        $dbConfig['password'] = $cfg['password'] ?? $dbConfig['password'];
    }
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

require_once $root . '/src/backend/Services/MailService.php';

use App\Services\MailService;

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$expiresAt = (new DateTimeImmutable('+' . $months . ' months'))->format('Y-m-d H:i:s');
$startDate = (new DateTimeImmutable())->format('Y-m-d H:i:s');
$plan = match ($months) {
    6 => '6m',
    24 => '12m',
    default => '12m',
};
$displayName = match ($companyTag) {
    'hyundai' => '현대자동차',
    'samsung' => '삼성',
    default => $companyTag,
};

$created = 0;
$updated = 0;
$mailer = $sendMail ? new MailService() : null;

$db->beginTransaction();
try {
    foreach ($emails as $email) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        $local = explode('@', $email)[0] ?? 'user';
        $nickname = mb_substr(preg_replace('/[^a-zA-Z0-9가-힣._-]/u', '', $local) ?: 'user', 0, 50);

        if ($existing) {
            $db->prepare("
                UPDATE users SET
                    password_hash = ?, company_tag = ?, is_subscribed = 1,
                    subscription_expires_at = ?, subscription_plan = ?,
                    subscription_start_date = ?, status = 'active', role = 'user'
                WHERE id = ?
            ")->execute([$passwordHash, $companyTag, $expiresAt, $plan, $startDate, (int) $existing['id']]);
            $updated++;
            echo "UPDATED {$email}\n";
        } else {
            $db->prepare("
                INSERT INTO users (
                    email, password_hash, nickname, role, status,
                    company_tag, is_subscribed, subscription_expires_at,
                    subscription_plan, subscription_start_date
                ) VALUES (?, ?, ?, 'user', 'active', ?, 1, ?, ?, ?)
            ")->execute([$email, $passwordHash, $nickname, $companyTag, $expiresAt, $plan, $startDate]);
            $created++;
            echo "CREATED {$email}\n";
        }

        $db->prepare("
            INSERT INTO corporate_otp_skip (email, company_tag, created_by)
            VALUES (?, ?, NULL)
            ON DUPLICATE KEY UPDATE company_tag = VALUES(company_tag)
        ")->execute([$email, $companyTag]);

        if ($mailer !== null) {
            try {
                $mailer->sendCorporateWelcomeEmail($email, $password, $displayName, $months);
                echo "  mail sent\n";
            } catch (Throwable $mailEx) {
                echo "  mail failed: " . $mailEx->getMessage() . "\n";
            }
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Done: created={$created}, updated={$updated}\n";

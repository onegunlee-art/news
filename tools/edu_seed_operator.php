<?php
/**
 * EDU 운영자 시드 — test@edu.com / 1234!!
 *
 * Usage: php tools/edu_seed_operator.php [--apply]
 * Without --apply: prints SQL/hash only (dry run)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';

$email = 'test@edu.com';
$password = '1234!!';
$displayName = 'EDU 운영자';
$hash = password_hash($password, PASSWORD_DEFAULT);

$apply = in_array('--apply', $argv ?? [], true);

echo "EDU operator seed\n";
echo "  email: {$email}\n";
echo "  password: {$password}\n";
echo "  hash: {$hash}\n\n";

if (!$apply) {
    echo "Dry run. Apply with: php tools/edu_seed_operator.php --apply\n";
    exit(0);
}

$sb = eduSupabase();
if (!$sb->isConfigured()) {
    fwrite(STDERR, "Supabase not configured\n");
    exit(1);
}

$existing = $sb->select('edu_operators', 'email=eq.' . rawurlencode($email), 1);
$row = [
    'email' => $email,
    'password_hash' => $hash,
    'display_name' => $displayName,
    'status' => 'active',
];

if (!empty($existing[0]['id'])) {
    $id = (string) $existing[0]['id'];
    $sb->update('edu_operators', 'id=eq.' . $id, $row);
    echo "Updated operator id={$id}\n";
} else {
    $inserted = $sb->insert('edu_operators', $row);
    $id = (string) ($inserted[0]['id'] ?? '?');
    echo "Created operator id={$id}\n";
}

echo "Done.\n";

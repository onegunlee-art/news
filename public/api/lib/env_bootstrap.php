<?php
/**
 * api.php 없이 직접 호출되는 public/api/*.php용 .env → putenv
 */

if (!function_exists('apiBootstrapEnvFromDotenv')) {
    function apiBootstrapEnvFromDotenv(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        // public/api/lib → 프로젝트 루트
        $envFile = dirname(__DIR__, 3) . '/.env';
        if (!is_file($envFile) || !is_readable($envFile)) {
            return;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || ($line[0] ?? '') === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }
}

apiBootstrapEnvFromDotenv();

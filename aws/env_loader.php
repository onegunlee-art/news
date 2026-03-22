<?php
/**
 * Auto-prepend: loads .env into getenv() / $_ENV for all PHP scripts.
 * Configured via PHP-FPM pool: php_value[auto_prepend_file]
 */
$_envFile = '/var/www/thegist/.env';
if (is_file($_envFile) && is_readable($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        if (strpos($_line, '=') !== false) {
            [$_k, $_v] = explode('=', $_line, 2);
            $_k = trim($_k);
            $_v = trim($_v, " \t\"'");
            if ($_k !== '' && getenv($_k) === false) {
                putenv("$_k=$_v");
                $_ENV[$_k] = $_v;
            }
        }
    }
}
unset($_envFile, $_line, $_k, $_v);

<?php
/**
 * GIST EDU agent autoload (case-sensitive Linux safe)
 */
declare(strict_types=1);

function eduLoadAgents(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }

    $root = eduFindProjectRoot();
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Services\\Edu\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = $root . 'src/backend/Services/edu/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });

    $registered = true;
}

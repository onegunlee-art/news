<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Youtube\\')) {
        return;
    }
    $relative = str_replace('Youtube\\', '', $class);
    $parts = explode('\\', $relative);
    
    // Filename is last part (keep original case)
    $filename = array_pop($parts);
    
    // All directories to lowercase (Linux compatibility: agents vs Agents, visuals vs Visuals)
    $dirs = array_map('strtolower', $parts);
    
    // Build path: lowercase dirs + original case filename
    $path = __DIR__ . '/';
    if (!empty($dirs)) {
        $path .= implode('/', $dirs) . '/';
    }
    $path .= $filename . '.php';
    
    if (is_file($path)) {
        require_once $path;
    }
});

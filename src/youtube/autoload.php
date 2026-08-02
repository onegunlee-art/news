<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Youtube\\')) {
        return;
    }
    $relative = str_replace('Youtube\\', '', $class);
    $relativePath = str_replace('\\', '/', $relative);
    
    // Try exact case first, then lowercase (for Linux compatibility)
    $path = __DIR__ . '/' . $relativePath . '.php';
    if (is_file($path)) {
        require_once $path;
        return;
    }
    
    // Try lowercase path (contracts vs Contracts)
    $pathLower = __DIR__ . '/' . strtolower($relativePath) . '.php';
    if (is_file($pathLower)) {
        require_once $pathLower;
        return;
    }
    
    // Try with first directory lowercase (Contracts/Project -> contracts/Project)
    $parts = explode('/', $relativePath);
    if (count($parts) > 1) {
        $parts[0] = strtolower($parts[0]);
        $pathMixed = __DIR__ . '/' . implode('/', $parts) . '.php';
        if (is_file($pathMixed)) {
            require_once $pathMixed;
        }
    }
});

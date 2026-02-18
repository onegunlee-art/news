<?php
/**
 * Agent System Autoloader
 * 
 * PSR-4 호환 오토로더
 * 
 * @package Agents
 * @author The Gist AI System
 * @version 1.0.0
 */

declare(strict_types=1);

spl_autoload_register(function (string $class) {
    $prefix = 'Agents\\';
    $baseDir = __DIR__ . '/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Directory mappings
    $mappings = [
        'Core' => 'core',
        'Models' => 'models',
        'Services' => 'services',
        'Agents' => 'agents',
        'Pipeline' => 'pipeline',
        'Tests' => 'tests'
    ];
    
    // Check each mapping
    foreach ($mappings as $namespace => $dir) {
        if (strpos($relativeClass, $namespace . '\\') === 0) {
            $subClass = substr($relativeClass, strlen($namespace) + 1);
            $file = $baseDir . $dir . '/' . str_replace('\\', '/', $subClass) . '.php';
            
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
    
    // Fallback: direct mapping
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Require core files
$coreFiles = [
    __DIR__ . '/core/AgentInterface.php',
    __DIR__ . '/core/BaseAgent.php',
    __DIR__ . '/models/AgentContext.php',
    __DIR__ . '/models/AgentResult.php',
    __DIR__ . '/models/ArticleData.php',
    __DIR__ . '/models/AnalysisResult.php',
    __DIR__ . '/services/OpenAIService.php',
    __DIR__ . '/services/GoogleTTSService.php',
    __DIR__ . '/services/WebScraperService.php',
    __DIR__ . '/services/SupabaseService.php',
    __DIR__ . '/services/RAGService.php',
    __DIR__ . '/services/PersonaService.php',
    __DIR__ . '/agents/ValidationAgent.php',
    __DIR__ . '/agents/AnalysisAgent.php',
    __DIR__ . '/agents/InterpretAgent.php',
    __DIR__ . '/agents/LearningAgent.php',
    __DIR__ . '/pipeline/AgentPipeline.php',
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

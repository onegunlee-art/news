<?php
/**
 * 데이터베이스 설정 파일 (예제)
 * 
 * 이 파일을 database.php로 복사하고 실제 값을 입력하세요.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'ailand',
    'username' => 'ailand',
    'password' => 'YOUR_DATABASE_PASSWORD',
    
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];

<?php
/**
 * storage/logs 디렉터리 쓰기 권한 확인
 * 브라우저에서 /test_storage_writable.php 로 열어 확인 후 삭제 권장
 */
header('Content-Type: text/html; charset=utf-8');

// 프로젝트 루트 기준 storage/logs 경로
$projectRoot = dirname(__DIR__);
$storageDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
$logsDir = $storageDir . DIRECTORY_SEPARATOR . 'logs';
$testFile = $logsDir . DIRECTORY_SEPARATOR . 'test_write_' . time() . '.txt';

$results = [];

// 1. storage 디렉터리 존재 여부
$results[] = [
    'name' => 'storage 디렉터리',
    'path' => $storageDir,
    'status' => is_dir($storageDir) ? '✅ 존재' : '❌ 없음',
    'writable' => is_dir($storageDir) && is_writable($storageDir) ? '✅ 쓰기 가능' : '❌ 쓰기 불가',
];

// 2. storage/logs 디렉터리 존재 여부
$logsExists = is_dir($logsDir);
if (!$logsExists) {
    $created = @mkdir($logsDir, 0755, true);
    $results[] = [
        'name' => 'storage/logs 디렉터리',
        'path' => $logsDir,
        'status' => $created ? '✅ 생성됨' : '❌ 생성 실패',
        'writable' => $created && is_writable($logsDir) ? '✅ 쓰기 가능' : '❌ 쓰기 불가',
    ];
} else {
    $results[] = [
        'name' => 'storage/logs 디렉터리',
        'path' => $logsDir,
        'status' => '✅ 존재',
        'writable' => is_writable($logsDir) ? '✅ 쓰기 가능' : '❌ 쓰기 불가',
    ];
}

// 3. 실제 쓰기 테스트
$writeSuccess = false;
$writeError = '';
if (is_dir($logsDir) && is_writable($logsDir)) {
    $testContent = 'Write test at ' . date('Y-m-d H:i:s') . "\n";
    $writeSuccess = @file_put_contents($testFile, $testContent, LOCK_EX);
    if ($writeSuccess === false) {
        $writeError = error_get_last()['message'] ?? 'Unknown error';
    } else {
        @unlink($testFile);
    }
}

$results[] = [
    'name' => '실제 파일 쓰기',
    'path' => $testFile,
    'status' => $writeSuccess !== false ? '✅ 성공' : '❌ 실패',
    'writable' => $writeSuccess !== false ? '✅ 쓰기 가능' : '❌ ' . $writeError,
];

// 4. 현재 프로세스 권한
$results[] = [
    'name' => 'PHP 프로세스 사용자',
    'path' => '-',
    'status' => function_exists('posix_getpwuid') && function_exists('posix_geteuid') 
        ? posix_getpwuid(posix_geteuid())['name'] ?? 'Unknown'
        : get_current_user(),
    'writable' => '-',
];

// 5. 디렉터리 권한
if (is_dir($logsDir)) {
    $perms = substr(sprintf('%o', fileperms($logsDir)), -4);
    $results[] = [
        'name' => 'storage/logs 권한',
        'path' => $logsDir,
        'status' => $perms,
        'writable' => $perms >= '0755' ? '✅ OK' : '⚠️ 권한 확인 필요',
    ];
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage 쓰기 권한 확인</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
            background: #f9fafb;
        }
        h1 { color: #111827; font-size: 1.5rem; margin-bottom: 0.5rem; }
        .warning { 
            background: #fef3c7; 
            border-left: 4px solid #f59e0b; 
            padding: 1rem; 
            margin: 1rem 0; 
            border-radius: 0.5rem;
        }
        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.5rem;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            border-radius: 0.5rem; 
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th { 
            background: #111827; 
            color: white; 
            padding: 0.75rem; 
            text-align: left; 
            font-weight: 600;
        }
        td { 
            padding: 0.75rem; 
            border-bottom: 1px solid #e5e7eb; 
        }
        tr:last-child td { border-bottom: none; }
        code { 
            background: #e5e7eb; 
            padding: 0.125rem 0.375rem; 
            border-radius: 0.25rem; 
            font-size: 0.875rem;
        }
        .path { 
            color: #6b7280; 
            font-size: 0.875rem; 
            word-break: break-all;
        }
    </style>
</head>
<body>
    <h1>📁 Storage 쓰기 권한 확인</h1>
    <p style="color: #6b7280; margin-bottom: 2rem;">
        API 로그 기능이 정상 동작하려면 <code>storage/logs</code> 디렉터리에 쓰기 권한이 필요합니다.
    </p>

    <?php if ($writeSuccess !== false): ?>
        <div class="success">
            <strong>✅ 쓰기 가능</strong><br>
            <code>storage/logs</code> 디렉터리에 파일을 쓸 수 있습니다.
        </div>
    <?php else: ?>
        <div class="warning">
            <strong>⚠️ 쓰기 불가</strong><br>
            <code>storage/logs</code> 디렉터리에 쓰기 권한이 없습니다. 
            FTP/호스팅 관리자에서 디렉터리 권한을 <code>755</code> 또는 <code>775</code>로 변경해 주세요.
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>항목</th>
                <th>경로</th>
                <th>상태</th>
                <th>쓰기 권한</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td class="path"><?= htmlspecialchars($r['path']) ?></td>
                <td><?= $r['status'] ?></td>
                <td><?= $r['writable'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color: #6b7280; margin-top: 2rem; font-size: 0.875rem;">
        ⚠️ <strong>보안:</strong> 확인 완료 후 이 파일(<code>test_storage_writable.php</code>)을 삭제하세요.
    </p>
</body>
</html>

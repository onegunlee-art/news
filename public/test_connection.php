<?php
/**
 * dothome 호스팅 연결 테스트 스크립트
 * 
 * PHP 버전, MySQL 연결, 파일 시스템 권한을 확인합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

/**
 * 테스트 결과를 저장하는 클래스
 */
final class TestResult
{
    private string $name;
    private bool $success;
    private string $message;
    private mixed $details;

    public function __construct(string $name, bool $success, string $message, mixed $details = null)
    {
        $this->name = $name;
        $this->success = $success;
        $this->message = $message;
        $this->details = $details;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'success' => $this->success,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}

/**
 * 호스팅 환경 테스트 클래스
 */
final class HostingTester
{
    /** @var TestResult[] */
    private array $results = [];

    /**
     * 모든 테스트 실행
     */
    public function runAllTests(): void
    {
        $this->testPhpVersion();
        $this->testRequiredExtensions();
        $this->testFilePermissions();
        $this->testMySqlConnection();
        $this->testSessionSupport();
        $this->testJsonSupport();
        $this->testCurlSupport();
    }

    /**
     * PHP 버전 테스트
     */
    private function testPhpVersion(): void
    {
        $currentVersion = PHP_VERSION;
        $requiredVersion = '8.0.0';
        $success = version_compare($currentVersion, $requiredVersion, '>=');

        $this->results[] = new TestResult(
            'PHP 버전',
            $success,
            $success ? "PHP {$currentVersion} (요구사항: >= {$requiredVersion})" : "PHP 버전이 낮습니다. 현재: {$currentVersion}, 필요: >= {$requiredVersion}",
            [
                'current' => $currentVersion,
                'required' => $requiredVersion,
                'sapi' => PHP_SAPI,
            ]
        );
    }

    /**
     * 필수 PHP 확장 모듈 테스트
     */
    private function testRequiredExtensions(): void
    {
        $requiredExtensions = [
            'pdo' => 'PDO (데이터베이스)',
            'pdo_mysql' => 'PDO MySQL',
            'json' => 'JSON',
            'curl' => 'cURL',
            'mbstring' => 'Multibyte String',
            'openssl' => 'OpenSSL',
        ];

        $loaded = [];
        $missing = [];

        foreach ($requiredExtensions as $ext => $name) {
            if (extension_loaded($ext)) {
                $loaded[] = $name;
            } else {
                $missing[] = $name;
            }
        }

        $success = empty($missing);
        $this->results[] = new TestResult(
            'PHP 확장 모듈',
            $success,
            $success ? "모든 필수 확장 모듈이 설치됨" : "누락된 확장 모듈: " . implode(', ', $missing),
            [
                'loaded' => $loaded,
                'missing' => $missing,
            ]
        );
    }

    /**
     * 파일 시스템 권한 테스트
     */
    private function testFilePermissions(): void
    {
        $testDir = __DIR__;
        $testFile = $testDir . '/.test_write_' . time();
        
        $canWrite = false;
        $canRead = false;
        
        // 쓰기 테스트
        if (@file_put_contents($testFile, 'test') !== false) {
            $canWrite = true;
            // 읽기 테스트
            if (@file_get_contents($testFile) === 'test') {
                $canRead = true;
            }
            @unlink($testFile);
        }

        $success = $canWrite && $canRead;
        $this->results[] = new TestResult(
            '파일 시스템 권한',
            $success,
            $success ? "읽기/쓰기 권한 정상" : "파일 시스템 권한 문제 발생",
            [
                'directory' => $testDir,
                'can_write' => $canWrite,
                'can_read' => $canRead,
            ]
        );
    }

    /**
     * MySQL 연결 테스트
     */
    private function testMySqlConnection(): void
    {
        // 설정 파일 경로 확인
        $configPath = dirname(__DIR__) . '/config/database.php';
        
        if (!file_exists($configPath)) {
            $this->results[] = new TestResult(
                'MySQL 연결',
                false,
                "설정 파일 없음: config/database.php를 생성해주세요",
                ['config_path' => $configPath]
            );
            return;
        }

        try {
            $config = require $configPath;
            
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'] ?? 'localhost',
                $config['port'] ?? '3306',
                $config['database'] ?? ''
            );

            $pdo = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            $this->results[] = new TestResult(
                'MySQL 연결',
                true,
                "MySQL 연결 성공 (버전: {$version})",
                [
                    'host' => $config['host'] ?? 'localhost',
                    'database' => $config['database'] ?? '',
                    'version' => $version,
                ]
            );
        } catch (PDOException $e) {
            $this->results[] = new TestResult(
                'MySQL 연결',
                false,
                "MySQL 연결 실패: " . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 세션 지원 테스트
     */
    private function testSessionSupport(): void
    {
        $sessionStarted = false;
        
        if (session_status() === PHP_SESSION_NONE) {
            $sessionStarted = @session_start();
        } else {
            $sessionStarted = true;
        }

        $this->results[] = new TestResult(
            '세션 지원',
            $sessionStarted,
            $sessionStarted ? "세션 지원 정상" : "세션 시작 실패",
            [
                'session_status' => session_status(),
                'session_save_path' => session_save_path(),
            ]
        );
    }

    /**
     * JSON 지원 테스트
     */
    private function testJsonSupport(): void
    {
        $testData = ['test' => '한글 테스트', 'number' => 123];
        $encoded = json_encode($testData, JSON_UNESCAPED_UNICODE);
        $decoded = json_decode($encoded, true);
        
        $success = $decoded === $testData;

        $this->results[] = new TestResult(
            'JSON 지원',
            $success,
            $success ? "JSON 인코딩/디코딩 정상" : "JSON 처리 오류",
            [
                'encoded' => $encoded,
                'decoded' => $decoded,
            ]
        );
    }

    /**
     * cURL 지원 테스트
     */
    private function testCurlSupport(): void
    {
        if (!function_exists('curl_init')) {
            $this->results[] = new TestResult(
                'cURL 지원',
                false,
                "cURL 확장이 설치되지 않음",
                null
            );
            return;
        }

        $curlVersion = curl_version();
        $this->results[] = new TestResult(
            'cURL 지원',
            true,
            "cURL 버전: " . ($curlVersion['version'] ?? 'unknown'),
            [
                'version' => $curlVersion['version'] ?? 'unknown',
                'ssl_version' => $curlVersion['ssl_version'] ?? 'unknown',
                'protocols' => $curlVersion['protocols'] ?? [],
            ]
        );
    }

    /**
     * 테스트 결과 반환
     * 
     * @return TestResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * 전체 테스트 성공 여부
     */
    public function isAllPassed(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->isSuccess()) {
                return false;
            }
        }
        return true;
    }
}

// 테스트 실행
$tester = new HostingTester();
$tester->runAllTests();
$results = $tester->getResults();
$allPassed = $tester->isAllPassed();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>호스팅 연결 테스트 - News 맥락 분석</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans KR', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #e8e8e8;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.8rem;
            color: #00d9ff;
            text-shadow: 0 0 20px rgba(0, 217, 255, 0.3);
        }
        .status-banner {
            text-align: center;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .status-success {
            background: linear-gradient(135deg, #0f3d0f 0%, #1a5a1a 100%);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }
        .status-fail {
            background: linear-gradient(135deg, #3d0f0f 0%, #5a1a1a 100%);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        .test-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .test-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        .test-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        .test-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .icon-success {
            background: #2ecc71;
            color: white;
        }
        .icon-fail {
            background: #e74c3c;
            color: white;
        }
        .test-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .test-message {
            color: #a0a0a0;
            margin-left: 2.5rem;
            font-size: 0.95rem;
        }
        .test-details {
            margin-top: 0.75rem;
            margin-left: 2.5rem;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.75rem;
            border-radius: 8px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
            color: #7fdbff;
            overflow-x: auto;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
        .footer a {
            color: #00d9ff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 News 맥락 분석 - 호스팅 연결 테스트</h1>
        
        <div class="status-banner <?php echo $allPassed ? 'status-success' : 'status-fail'; ?>">
            <?php echo $allPassed ? '✅ 모든 테스트 통과! 호스팅 환경이 정상입니다.' : '⚠️ 일부 테스트 실패. 아래 결과를 확인해주세요.'; ?>
        </div>

        <?php foreach ($results as $result): 
            $data = $result->toArray();
        ?>
        <div class="test-card">
            <div class="test-header">
                <div class="test-icon <?php echo $data['success'] ? 'icon-success' : 'icon-fail'; ?>">
                    <?php echo $data['success'] ? '✓' : '✕'; ?>
                </div>
                <span class="test-name"><?php echo htmlspecialchars($data['name']); ?></span>
            </div>
            <div class="test-message">
                <?php echo htmlspecialchars($data['message']); ?>
            </div>
            <?php if ($data['details']): ?>
            <div class="test-details">
                <pre><?php echo htmlspecialchars(json_encode($data['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="footer">
            <p>테스트 시간: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>서버: <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
        </div>
    </div>
</body>
</html>

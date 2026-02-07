<?php
/**
 * 카카오 로그인 API 테스트 페이지
 * 
 * 카카오 API 설정 및 연동 상태를 확인합니다.
 */

header('Content-Type: text/html; charset=utf-8');

// 설정 로드
$kakaoConfig = require dirname(__DIR__, 3) . '/config/kakao.php';
$appConfig = require dirname(__DIR__, 3) . '/config/app.php';

$restApiKey = $kakaoConfig['rest_api_key'] ?? '';
$redirectUri = $kakaoConfig['oauth']['redirect_uri'] ?? '';

// 로그인 URL 생성
$state = bin2hex(random_bytes(16));
$loginUrl = $kakaoConfig['oauth']['authorize_url'] . '?' . http_build_query([
    'client_id' => $restApiKey,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => implode(',', $kakaoConfig['oauth']['scope'] ?? []),
    'state' => $state,
]);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>카카오 로그인 API 테스트</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: #fff;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2rem;
            background: linear-gradient(90deg, #00d9ff, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status.ok {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .status.error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .status.warning {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #9ca3af; }
        .info-value { 
            color: #fff;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }
        .info-value.masked {
            color: #60a5fa;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        .btn-kakao {
            background: #FEE500;
            color: #3C1E1E;
        }
        .btn-kakao:hover {
            background: #FDD835;
            transform: translateY(-2px);
        }
        .btn-outline {
            background: transparent;
            color: #00d9ff;
            border: 1px solid #00d9ff;
        }
        .btn-outline:hover {
            background: rgba(0, 217, 255, 0.1);
        }
        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 32px;
        }
        .checklist {
            list-style: none;
        }
        .checklist li {
            padding: 12px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .checklist .icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .checklist .icon.check {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .checklist .icon.x {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        code {
            background: rgba(0,0,0,0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .highlight {
            background: rgba(0, 217, 255, 0.1);
            border-left: 3px solid #00d9ff;
            padding: 16px;
            margin: 16px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 카카오 로그인 API 테스트</h1>
        
        <!-- 현재 설정 상태 -->
        <div class="card">
            <h2>
                ⚙️ 현재 설정 상태
                <?php if (!empty($restApiKey)): ?>
                    <span class="status ok">✓ 설정됨</span>
                <?php else: ?>
                    <span class="status error">✗ 미설정</span>
                <?php endif; ?>
            </h2>
            
            <div class="info-row">
                <span class="info-label">REST API Key</span>
                <span class="info-value masked">
                    <?php 
                    if (!empty($restApiKey)) {
                        echo substr($restApiKey, 0, 8) . '****' . substr($restApiKey, -4);
                    } else {
                        echo '<span style="color:#ef4444">미설정</span>';
                    }
                    ?>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Redirect URI</span>
                <span class="info-value"><?= htmlspecialchars($redirectUri) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">요청 권한 (Scope)</span>
                <span class="info-value"><?= htmlspecialchars(implode(', ', $kakaoConfig['oauth']['scope'] ?? [])) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">PHP 버전</span>
                <span class="info-value"><?= PHP_VERSION ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">cURL 지원</span>
                <span class="info-value">
                    <?php if (function_exists('curl_init')): ?>
                        <span style="color:#22c55e">✓ 지원</span>
                    <?php else: ?>
                        <span style="color:#ef4444">✗ 미지원</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <!-- 설정 방법 -->
        <div class="card">
            <h2>📋 설정 체크리스트</h2>
            
            <ul class="checklist">
                <li>
                    <span class="icon <?= !empty($restApiKey) ? 'check' : 'x' ?>">
                        <?= !empty($restApiKey) ? '✓' : '✗' ?>
                    </span>
                    <div>
                        <strong>REST API Key 설정</strong>
                        <p style="color:#9ca3af; font-size:0.875rem; margin-top:4px;">
                            <code>config/kakao.php</code> 파일에 REST API 키를 입력하세요.
                        </p>
                    </div>
                </li>
                
                <li>
                    <span class="icon <?= strpos($redirectUri, 'localhost') === false ? 'check' : 'x' ?>">
                        <?= strpos($redirectUri, 'localhost') === false ? '✓' : '!' ?>
                    </span>
                    <div>
                        <strong>Redirect URI 등록</strong>
                        <p style="color:#9ca3af; font-size:0.875rem; margin-top:4px;">
                            카카오 Developers에서 다음 URI를 등록하세요:
                            <br><code><?= htmlspecialchars($redirectUri) ?></code>
                        </p>
                    </div>
                </li>
                
                <li>
                    <span class="icon check">✓</span>
                    <div>
                        <strong>동의항목 설정</strong>
                        <p style="color:#9ca3af; font-size:0.875rem; margin-top:4px;">
                            카카오 Developers → 앱 설정 → 동의항목에서 필수 항목을 설정하세요:
                            <br>• 닉네임 (필수)
                            <br>• 프로필 사진 (선택)
                            <br>• 카카오계정(이메일) (선택)
                        </p>
                    </div>
                </li>
            </ul>
        </div>
        
        <!-- 카카오 Developers 안내 -->
        <div class="card">
            <h2>🛠️ 카카오 Developers 설정 방법</h2>
            
            <div class="highlight">
                <ol style="margin-left: 20px; line-height: 2;">
                    <li><a href="https://developers.kakao.com" target="_blank" style="color:#00d9ff">developers.kakao.com</a> 접속 후 로그인</li>
                    <li>내 애플리케이션 → 애플리케이션 추가하기</li>
                    <li>앱 이름: <strong>News Context</strong> (원하는 이름)</li>
                    <li>앱 키 → <strong>REST API 키</strong> 복사</li>
                    <li><code>config/kakao.php</code> 파일 수정:
                        <pre style="background:#1a1a2e; padding:12px; border-radius:8px; margin-top:8px; overflow-x:auto;">
'rest_api_key' => 'YOUR_REST_API_KEY_HERE',</pre>
                    </li>
                    <li>플랫폼 → Web → 사이트 도메인 추가: <code>https://www.thegist.co.kr</code></li>
                    <li>카카오 로그인 → 활성화 설정: ON</li>
                    <li>Redirect URI 등록: <code><?= htmlspecialchars($redirectUri) ?></code></li>
                    <li>동의항목 → 필수 항목 설정</li>
                </ol>
            </div>
        </div>
        
        <!-- 테스트 버튼 -->
        <div class="actions">
            <?php if (!empty($restApiKey)): ?>
                <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-kakao">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
                    </svg>
                    카카오 로그인 테스트
                </a>
            <?php else: ?>
                <button class="btn btn-kakao" disabled style="opacity:0.5; cursor:not-allowed;">
                    REST API Key를 먼저 설정하세요
                </button>
            <?php endif; ?>
            
            <a href="https://developers.kakao.com/console/app" target="_blank" class="btn btn-outline">
                카카오 Developers 이동 →
            </a>
            
            <a href="/" class="btn btn-outline">
                ← 홈으로 돌아가기
            </a>
        </div>
    </div>
</body>
</html>

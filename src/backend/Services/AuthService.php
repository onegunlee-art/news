<?php
/**
 * 인증 서비스 클래스
 * 
 * 사용자 인증 및 세션 관리를 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Utils\JWT;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * AuthService 클래스
 * 
 * JWT 기반 인증 처리를 담당합니다.
 */
final class AuthService
{
    private const LOGIN_OTP_EXPIRY_MINUTES = 10;
    private const LOGIN_OTP_MAX_ATTEMPTS = 5;
    private const LOGIN_OTP_RESEND_SECONDS = 60;

    private UserRepository $userRepository;
    private JWT $jwt;
    private KakaoAuthService $kakaoAuth;
    private GoogleAuthService $googleAuth;

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->jwt = new JWT();
        $this->kakaoAuth = new KakaoAuthService();
        $this->googleAuth = new GoogleAuthService();
    }

    /**
     * 카카오 로그인 URL 반환
     */
    public function getKakaoLoginUrl(): string
    {
        $state = $this->buildSignedOAuthState();

        return $this->kakaoAuth->getAuthorizationUrl($state);
    }

    /**
     * 카카오 콜백 처리 (인가 코드로 로그인)
     */
    public function handleKakaoCallback(string $code, ?string $state = null): array
    {
        $this->verifySignedOAuthState($state);

        // 1. 인가 코드로 액세스 토큰 발급
        $tokenData = $this->kakaoAuth->getAccessToken($code);
        
        // 2. 액세스 토큰으로 사용자 정보 조회
        $kakaoUser = $this->kakaoAuth->getUserInfo($tokenData['access_token']);
        
        // 3. 사용자 생성 또는 업데이트
        [$userId, $isNewUser] = $this->userRepository->createOrUpdateFromKakao($kakaoUser);
        
        // 4. 사용자 정보 조회
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            throw new RuntimeException('Failed to retrieve user data');
        }

        // 5. JWT 토큰 발급
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        
        $refreshToken = $this->jwt->createRefreshToken($userId);
        
        // 6. 리프레시 토큰 저장
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        
        return [
            'user' => User::fromArray($userData)->toJson(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'is_new_user' => $isNewUser,
        ];
    }

    /**
     * Google 로그인 URL 생성
     */
    public function getGoogleLoginUrl(): string
    {
        $state = $this->buildSignedOAuthState();

        return $this->googleAuth->getAuthorizationUrl($state);
    }

    /**
     * Google 콜백 처리 (인가 코드로 로그인)
     */
    public function handleGoogleCallback(string $code, ?string $state = null): array
    {
        $this->verifySignedOAuthState($state);

        $tokenData = $this->googleAuth->getAccessToken($code);
        $googleUser = $this->googleAuth->getUserInfo($tokenData['access_token']);
        [$userId, $isNewUser] = $this->userRepository->createOrUpdateFromGoogle($googleUser);
        $userData = $this->userRepository->findById($userId);
        if (!$userData) {
            throw new RuntimeException('Failed to retrieve user data');
        }
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        $refreshToken = $this->jwt->createRefreshToken($userId);
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        return [
            'user' => User::fromArray($userData)->toJson(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'is_new_user' => $isNewUser,
        ];
    }

    /**
     * 토큰 갱신
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        // 1. 리프레시 토큰 검증
        $tokenData = $this->userRepository->findRefreshToken($refreshToken);
        
        if (!$tokenData) {
            throw new RuntimeException('Invalid or expired refresh token');
        }
        
        // 2. JWT 페이로드 검증
        $payload = $this->jwt->decode($refreshToken);
        
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new RuntimeException('Invalid token type');
        }
        
        $userId = (int) $payload['user_id'];
        
        // 3. 사용자 정보 조회
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData || $userData['status'] !== 'active') {
            throw new RuntimeException('User not found or inactive');
        }
        
        // 4. 기존 리프레시 토큰 폐기
        $this->userRepository->revokeRefreshToken($refreshToken);
        
        // 5. 새 토큰 발급
        $newAccessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        
        $newRefreshToken = $this->jwt->createRefreshToken($userId);
        
        // 6. 새 리프레시 토큰 저장
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $newRefreshToken, $refreshExpiry);
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
    }

    /**
     * 로그아웃
     */
    public function logout(string $accessToken, ?string $refreshToken = null): bool
    {
        try {
            // 액세스 토큰에서 사용자 ID 추출
            $payload = $this->jwt->decode($accessToken);
            $userId = (int) $payload['user_id'];
            
            // 리프레시 토큰이 제공된 경우 해당 토큰만 폐기
            if ($refreshToken) {
                $this->userRepository->revokeRefreshToken($refreshToken);
            } else {
                // 아니면 모든 토큰 폐기
                $this->userRepository->revokeAllTokens($userId);
            }
            
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 토큰에서 사용자 정보 추출
     */
    public function getUserFromToken(string $accessToken): ?array
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            if (($payload['type'] ?? '') !== 'access') {
                return null;
            }
            
            $userId = (int) $payload['user_id'];
            $userData = $this->userRepository->findById($userId);
            
            if (!$userData || $userData['status'] !== 'active') {
                return null;
            }
            
            $isSubscribed = (bool) ($userData['is_subscribed'] ?? false);
            $expiresAt = $userData['subscription_expires_at'] ?? null;

            if ($isSubscribed && $expiresAt && strtotime($expiresAt) < time()) {
                $db = Database::getInstance();
                $db->executeQuery(
                    'UPDATE users SET is_subscribed = 0 WHERE id = :id',
                    ['id' => $userId]
                );
                $userData['is_subscribed'] = 0;
            }

            return User::fromArray($userData)->toJson();
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * 토큰 유효성 검증
     */
    public function validateAccessToken(string $accessToken): bool
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            return ($payload['type'] ?? '') === 'access';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 현재 인증된 사용자 ID 반환
     */
    public function getAuthenticatedUserId(string $accessToken): ?int
    {
        try {
            $payload = $this->jwt->decode($accessToken);
            
            if (($payload['type'] ?? '') !== 'access') {
                return null;
            }
            
            return (int) $payload['user_id'];
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * 사용자 ID로 사용자 정보 조회
     */
    public function getUserById(int $userId): ?array
    {
        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            return null;
        }
        
        return User::fromArray($userData)->toJson();
    }

    /**
     * 이메일/비밀번호 로그인 1단계 (관리자는 즉시 토큰, 일반 사용자는 OTP 필요)
     *
     * @return array{requires_otp: true, otp_session: string}|array{user: mixed, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function startEmailLogin(string $email, string $password): array
    {
        $userData = $this->assertActiveUserWithPassword($email, $password);
        $userId = (int) $userData['id'];
        $role = (string) ($userData['role'] ?? 'user');

        if ($role === 'admin') {
            return $this->completeEmailLoginIssueTokens($userData);
        }

        $db = Database::getInstance();
        $sessionToken = bin2hex(random_bytes(32));
        $plainCode = (string) random_int(100000, 999999);
        $codeHash = password_hash($plainCode, PASSWORD_DEFAULT);
        if ($codeHash === false) {
            throw new RuntimeException('로그인 처리 중 오류가 발생했습니다.');
        }
        $expiresAt = (new \DateTimeImmutable('+' . self::LOGIN_OTP_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        try {
            $db->executeQuery(
                'UPDATE email_login_challenges SET consumed_at = :now WHERE user_id = :uid AND consumed_at IS NULL',
                ['now' => $now, 'uid' => $userId]
            );
        } catch (PDOException) {
            throw new RuntimeException('로그인 인증 설정이 완료되지 않았습니다. 관리자에게 문의하세요.');
        }

        try {
            $challengeId = $db->insert('email_login_challenges', [
                'token' => $sessionToken,
                'user_id' => $userId,
                'code_hash' => $codeHash,
                'expires_at' => $expiresAt,
                'attempts' => 0,
                'consumed_at' => null,
                'last_code_sent_at' => $now,
            ]);
        } catch (PDOException) {
            throw new RuntimeException('로그인 인증 설정이 완료되지 않았습니다. 관리자에게 문의하세요.');
        }

        $userEmail = (string) ($userData['email'] ?? $email);
        try {
            $this->sendLoginOtpEmail($userEmail, $plainCode);
        } catch (Throwable) {
            $db->executeQuery('DELETE FROM email_login_challenges WHERE id = :id', ['id' => $challengeId]);

            throw new RuntimeException('인증 메일 발송에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }

        return [
            'requires_otp' => true,
            'otp_session' => $sessionToken,
        ];
    }

    /**
     * 이메일 로그인 OTP 검증 후 토큰 발급
     *
     * @return array{user: mixed, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function completeEmailLoginWithOtp(string $otpSession, string $code): array
    {
        $otpSession = trim($otpSession);
        $this->assertOpaqueSessionToken($otpSession);
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            throw new RuntimeException('인증 코드는 6자리 숫자입니다.');
        }

        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id, user_id, code_hash, expires_at, attempts, consumed_at FROM email_login_challenges WHERE token = :t LIMIT 1',
            ['t' => $otpSession]
        );
        if (!$row) {
            throw new RuntimeException('유효하지 않은 로그인 세션입니다. 처음부터 다시 로그인해주세요.');
        }
        if ($row['consumed_at'] !== null) {
            throw new RuntimeException('이미 사용되었거나 만료된 로그인 세션입니다.');
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            throw new RuntimeException('인증 코드가 만료되었습니다. 처음부터 다시 로그인해주세요.');
        }
        if ((int) $row['attempts'] >= self::LOGIN_OTP_MAX_ATTEMPTS) {
            throw new RuntimeException('인증 시도 횟수를 초과했습니다. 처음부터 다시 로그인해주세요.');
        }

        if (!password_verify($code, (string) $row['code_hash'])) {
            $db->executeQuery(
                'UPDATE email_login_challenges SET attempts = attempts + 1 WHERE id = :id',
                ['id' => $row['id']]
            );
            $newAttempts = (int) $row['attempts'] + 1;
            if ($newAttempts >= self::LOGIN_OTP_MAX_ATTEMPTS) {
                $db->executeQuery(
                    'UPDATE email_login_challenges SET consumed_at = NOW() WHERE id = :id',
                    ['id' => $row['id']]
                );
            }

            throw new RuntimeException('인증 코드가 올바르지 않습니다.');
        }

        $db->executeQuery(
            'UPDATE email_login_challenges SET consumed_at = NOW() WHERE id = :id',
            ['id' => $row['id']]
        );

        $userData = $this->userRepository->findById((int) $row['user_id']);
        if (!$userData || ($userData['status'] ?? '') !== 'active') {
            throw new RuntimeException('계정을 확인할 수 없습니다.');
        }

        return $this->completeEmailLoginIssueTokens($userData);
    }

    /**
     * 로그인 OTP 재발송 (동일 otp_session)
     */
    public function resendLoginOtp(string $otpSession): void
    {
        $otpSession = trim($otpSession);
        $this->assertOpaqueSessionToken($otpSession);
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id, user_id, expires_at, consumed_at, last_code_sent_at FROM email_login_challenges WHERE token = :t LIMIT 1',
            ['t' => $otpSession]
        );
        if (!$row || $row['consumed_at'] !== null) {
            throw new RuntimeException('유효하지 않은 로그인 세션입니다. 처음부터 다시 로그인해주세요.');
        }
        if (strtotime((string) $row['expires_at']) <= time()) {
            throw new RuntimeException('로그인 세션이 만료되었습니다. 처음부터 다시 로그인해주세요.');
        }
        $lastSent = strtotime((string) $row['last_code_sent_at']);
        if ($lastSent !== false && (time() - $lastSent) < self::LOGIN_OTP_RESEND_SECONDS) {
            throw new RuntimeException('인증 메일은 1분에 한 번만 요청할 수 있습니다.');
        }

        $userData = $this->userRepository->findById((int) $row['user_id']);
        if (!$userData) {
            throw new RuntimeException('계정을 확인할 수 없습니다.');
        }
        $userEmail = (string) $userData['email'];

        $plainCode = (string) random_int(100000, 999999);
        $codeHash = password_hash($plainCode, PASSWORD_DEFAULT);
        if ($codeHash === false) {
            throw new RuntimeException('인증 코드 재발송에 실패했습니다.');
        }
        $expiresAt = (new \DateTimeImmutable('+' . self::LOGIN_OTP_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // 메일 성공 후에만 DB 갱신 (발송 실패 시 기존 코드·쿨다운 유지)
        $this->sendLoginOtpEmail($userEmail, $plainCode);

        $db->executeQuery(
            'UPDATE email_login_challenges SET code_hash = :h, expires_at = :e, attempts = 0, last_code_sent_at = :n WHERE id = :id',
            ['h' => $codeHash, 'e' => $expiresAt, 'n' => $now, 'id' => $row['id']]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function assertActiveUserWithPassword(string $email, string $password): array
    {
        $userData = $this->userRepository->findByEmail($email);

        if (!$userData || $userData['status'] !== 'active') {
            throw new RuntimeException('이메일 또는 비밀번호가 올바르지 않습니다.');
        }

        $passwordHash = $userData['password_hash'] ?? null;
        if (!$passwordHash || !password_verify($password, $passwordHash)) {
            throw new RuntimeException('이메일 또는 비밀번호가 올바르지 않습니다.');
        }

        return $userData;
    }

    /**
     * @param array<string, mixed> $userData
     *
     * @return array{user: mixed, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function completeEmailLoginIssueTokens(array $userData): array
    {
        $userId = (int) $userData['id'];
        $this->userRepository->updateLastLogin($userId);

        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        $refreshToken = $this->jwt->createRefreshToken($userId);
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);

        $user = User::fromArray($userData)->toJson();

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
    }

    private function sendLoginOtpEmail(string $email, string $plainCode): void
    {
        $mailer = new MailService();
        $subject = '[The Gist] 로그인 인증 코드';
        $body = "로그인 인증 코드: {$plainCode}\n\n" . self::LOGIN_OTP_EXPIRY_MINUTES . "분 내에 입력해주세요.";
        $html = '<p>로그인 인증 코드: <strong>' . htmlspecialchars($plainCode) . '</strong></p><p>'
            . self::LOGIN_OTP_EXPIRY_MINUTES . '분 내에 입력해주세요.</p>';
        if (!$mailer->send($email, $subject, $body, $html)) {
            throw new RuntimeException('인증 메일 발송에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }
    }

    private function assertOpaqueSessionToken(string $token): void
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            throw new RuntimeException('유효하지 않은 로그인 세션입니다.');
        }
    }

    /**
     * 이메일/비밀번호 회원가입 (이메일 인증 완료된 경우만 허용)
     */
    public function registerWithEmail(string $email, string $password, string $nickname): array
    {
        $db = Database::getInstance();
        $verified = $db->fetchOne(
            'SELECT id FROM email_verifications WHERE email = :email AND verified_at IS NOT NULL AND used_at IS NULL AND verified_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) LIMIT 1',
            ['email' => $email]
        );
        if (!$verified) {
            throw new RuntimeException('이메일 인증을 먼저 완료해주세요.');
        }

        if ($this->userRepository->findByEmail($email)) {
            throw new RuntimeException('이미 사용 중인 이메일입니다.');
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('비밀번호 처리 중 오류가 발생했습니다.');
        }
        
        $userId = $this->userRepository->createWithPassword($email, $passwordHash, $nickname);
        $db->executeQuery('UPDATE email_verifications SET used_at = NOW() WHERE id = :id', ['id' => $verified['id']]);

        $userData = $this->userRepository->findById($userId);
        
        if (!$userData) {
            throw new RuntimeException('회원가입 처리 중 오류가 발생했습니다.');
        }
        
        $accessToken = $this->jwt->createAccessToken($userId, [
            'nickname' => $userData['nickname'],
            'role' => $userData['role'],
        ]);
        $refreshToken = $this->jwt->createRefreshToken($userId);
        $refreshExpiry = new \DateTimeImmutable('+7 days');
        $this->userRepository->saveRefreshToken($userId, $refreshToken, $refreshExpiry);
        
        $user = User::fromArray($userData)->toJson();
        
        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
    }

    /**
     * 이메일 인증 코드 발송
     */
    public function sendVerificationCode(string $email): void
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('유효한 이메일 주소를 입력해주세요.');
        }
        $db = Database::getInstance();
        $existing = $db->fetchOne('SELECT id, created_at FROM email_verifications WHERE email = :email ORDER BY id DESC LIMIT 1', ['email' => $email]);
        if ($existing && (time() - strtotime($existing['created_at'])) < 60) {
            throw new RuntimeException('인증 메일은 1분에 한 번만 요청할 수 있습니다.');
        }
        $code = (string) random_int(100000, 999999);
        $expiresAt = (new \DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
        $mailer = new MailService();
        $subject = '[The Gist] 이메일 인증 코드';
        $body = "인증 코드: {$code}\n\n10분 내에 입력해주세요.";
        $html = '<p>인증 코드: <strong>' . htmlspecialchars($code) . '</strong></p><p>10분 내에 입력해주세요.</p>';
        // 발송 성공 후에만 DB 기록 (실패 시 행이 없어 재시도·쿨다운에 걸리지 않음)
        if (!$mailer->send($email, $subject, $body, $html)) {
            throw new RuntimeException('이메일 발송에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }
        $db->insert('email_verifications', [
            'email' => $email,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * 이메일 인증 코드 검증
     */
    public function verifyEmailCode(string $email, string $code): bool
    {
        $email = trim(strtolower($email));
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            throw new RuntimeException('인증 코드는 6자리 숫자입니다.');
        }
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id FROM email_verifications WHERE email = :email AND code = :code AND expires_at > NOW() AND verified_at IS NULL LIMIT 1',
            ['email' => $email, 'code' => $code]
        );
        if (!$row) {
            throw new RuntimeException('인증 코드가 올바르지 않거나 만료되었습니다.');
        }
        $db->executeQuery('UPDATE email_verifications SET verified_at = NOW() WHERE id = :id', ['id' => $row['id']]);
        return true;
    }

    /** OAuth state(구글·카카오 공통) 유효 시간(초) — 세션 없이 HMAC만으로 검증 */
    private const OAUTH_STATE_MAX_AGE_SECONDS = 600;

    private function getOAuthStateSecret(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        $secret = (string) ($config['security']['jwt_secret'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET(OAuth state 서명용)이 설정되지 않았습니다.');
        }
        $cached = $secret;

        return $cached;
    }

    private function buildSignedOAuthState(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $ts = (string) time();
        $payload = $nonce . '.' . $ts;

        return $payload . '.' . hash_hmac('sha256', $payload, $this->getOAuthStateSecret());
    }

    private function verifySignedOAuthState(?string $state): void
    {
        if ($state === null || $state === '') {
            throw new RuntimeException('OAuth state가 없습니다. 다시 시도해 주세요.');
        }
        $parts = explode('.', $state, 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('OAuth state 검증 실패. 다시 시도해 주세요.');
        }
        [$nonce, $tsStr, $sig] = $parts;
        if ($nonce === '' || !ctype_digit($tsStr)) {
            throw new RuntimeException('OAuth state 검증 실패. 다시 시도해 주세요.');
        }
        $ts = (int) $tsStr;
        if (abs(time() - $ts) > self::OAUTH_STATE_MAX_AGE_SECONDS) {
            throw new RuntimeException('OAuth state가 만료되었습니다. 다시 시도해 주세요.');
        }
        $payload = $nonce . '.' . $tsStr;
        $expected = hash_hmac('sha256', $payload, $this->getOAuthStateSecret());
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('OAuth state 검증 실패. 다시 시도해 주세요.');
        }
    }
}

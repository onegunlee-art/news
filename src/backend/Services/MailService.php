<?php
/**
 * 메일 발송 서비스 (Resend API 또는 PHP mail fallback)
 */

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class MailService
{
    private array $config;
    private string $driver;

    public function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/mail.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException('Mail configuration not found');
        }
        $this->config = require $configPath;
        $this->driver = $this->config['driver'] ?? 'mail';
    }

    /**
     * 단일 수신자에게 이메일 발송
     */
    public function send(string $to, string $subject, string $textBody, string $htmlBody = ''): bool
    {
        if ($this->driver === 'resend' && !empty($this->config['resend']['api_key'])) {
            return $this->sendViaResend($to, $subject, $textBody, $htmlBody);
        }
        return $this->sendViaMail($to, $subject, $textBody);
    }

    /**
     * 첨부 파일과 함께 이메일 발송 (Resend API 전용)
     * 
     * @param string $to 수신자 이메일
     * @param string $subject 제목
     * @param string $textBody 텍스트 본문
     * @param string $htmlBody HTML 본문
     * @param array $attachments 첨부 파일 배열 [['filename' => 'file.pdf', 'content' => 'base64...'], ...]
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $textBody,
        string $htmlBody,
        array $attachments
    ): array {
        if (empty($this->config['resend']['api_key'])) {
            return [
                'success' => false,
                'error' => 'RESEND_API_KEY가 설정되지 않았습니다. 첨부 파일 발송은 Resend API가 필요합니다.',
            ];
        }

        return $this->sendViaResendWithAttachment($to, $subject, $textBody, $htmlBody, $attachments);
    }

    /**
     * Resend API로 첨부 파일 포함 이메일 발송
     */
    private function sendViaResendWithAttachment(
        string $to,
        string $subject,
        string $textBody,
        string $htmlBody,
        array $attachments
    ): array {
        $apiKey = $this->config['resend']['api_key'];
        $from = $this->config['from']['address'] ?? 'noreply@thegist.co.kr';
        $fromName = $this->config['from']['name'] ?? 'The Gist';

        $payload = [
            'from' => $fromName . ' <' . $from . '>',
            'to' => [$to],
            'subject' => $subject,
            'text' => $textBody,
        ];

        if ($htmlBody !== '') {
            $payload['html'] = $htmlBody;
        }

        if ($attachments !== []) {
            $payload['attachments'] = array_map(function ($att) {
                return [
                    'filename' => $att['filename'] ?? 'attachment',
                    'content' => $att['content'],
                ];
            }, $attachments);
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return [
                'success' => false,
                'error' => 'CURL 오류: ' . $curlError,
            ];
        }

        $decoded = $response ? json_decode($response, true) : [];

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message_id' => $decoded['id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $decoded['message'] ?? ('HTTP ' . $httpCode . ' 오류'),
        ];
    }

    /**
     * Resend API 키 설정 여부 확인
     */
    public function isResendConfigured(): bool
    {
        return !empty($this->config['resend']['api_key']);
    }

    private function sendViaResend(string $to, string $subject, string $textBody, string $htmlBody): bool
    {
        $apiKey = $this->config['resend']['api_key'];
        $from = $this->config['from']['address'] ?? 'noreply@thegist.co.kr';
        $fromName = $this->config['from']['name'] ?? 'The Gist';

        $payload = [
            'from' => $fromName . ' <' . $from . '>',
            'to' => [$to],
            'subject' => $subject,
            'text' => $textBody,
        ];
        if ($htmlBody !== '') {
            $payload['html'] = $htmlBody;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        $decoded = $response ? json_decode($response, true) : [];
        throw new RuntimeException('이메일 발송 실패: ' . ($decoded['message'] ?? 'Unknown error'));
    }

    private function sendViaMail(string $to, string $subject, string $textBody): bool
    {
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . ($this->config['from']['name'] ?? 'The Gist') . ' <' . ($this->config['from']['address'] ?? 'noreply@thegist.co.kr') . '>',
        ];
        return (bool) @mail($to, $subjectEncoded, $textBody, implode("\r\n", $headers));
    }
}

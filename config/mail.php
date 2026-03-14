<?php
/**
 * 메일 발송 설정 (Resend API 또는 PHP mail fallback)
 *
 * Resend 사용 시: https://resend.com 에서 API Key 발급 후 RESEND_API_KEY 설정
 */

return [
    'driver' => getenv('MAIL_DRIVER') ?: 'resend', // 'resend' | 'mail'
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@thegist.co.kr',
        'name' => getenv('MAIL_FROM_NAME') ?: 'The Gist',
    ],
    'resend' => [
        'api_key' => getenv('RESEND_API_KEY') ?: '',
    ],
];

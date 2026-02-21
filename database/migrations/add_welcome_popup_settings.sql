-- 가입 환영 팝업 설정
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('welcome_popup_message', 'The Gist 가입을 감사드립니다.', 'string', '가입 완료 팝업 메시지'),
    ('welcome_popup_title', '{name}님', 'string', '이름 표시 형식 ({name}=닉네임)'),
    ('promo_code_prefix', 'WELCOME', 'string', '프로모션 코드 접두사')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

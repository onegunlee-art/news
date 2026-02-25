-- 사이트 공개 설정 (My Page/푸터/문의용)
-- contact_email: 문의하기 수신 이메일, copyright_text: 저작권 문구, the_gist_vision: The Gist 비전
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('contact_email', 'onegunlee@gmail.com', 'string', '문의하기 수신 이메일 주소'),
    ('copyright_text', '', 'string', '저작권 문구 (비어 있으면 © {year} The Gist 사용)'),
    ('the_gist_vision', 'Gisters, Becoming Leaders', 'string', 'The Gist 회사 비전 문구')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 개인정보처리방침, 이용약관 설정 추가
-- settings 테이블에 key로 저장 (이미 존재하는 테이블 활용)
-- value는 Admin에서 수정 가능
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('privacy_policy', '', 'string', '개인정보처리방침 전문'),
    ('terms_of_service', '', 'string', '이용약관 전문')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

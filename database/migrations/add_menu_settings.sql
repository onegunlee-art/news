-- 메뉴 설정 (탭 라벨, 하위 카테고리 라벨) - Admin에서 수정 가능
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('menu_tabs', '[{"key":"latest","label":"최신"},{"key":"diplomacy","label":"외교"},{"key":"economy","label":"경제"},{"key":"special","label":"특집"},{"key":"popular","label":"인기"}]', 'json', '홈 탭 메뉴 (key: API 카테고리, label: 표시 이름)'),
    ('menu_subcategories', '{"politics_diplomacy":"Politics/Diplomacy","economy_industry":"Economy/Industry","society":"Society","security_conflict":"Security/Conflict","environment":"Environment","science_technology":"Science/Technology","culture":"Culture","health_development":"Health/Development"}', 'json', '하위 카테고리 표시 라벨')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

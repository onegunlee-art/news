-- 홈 탭: 인기(popular) → 과거 특집(archive)
UPDATE `settings`
SET `value` = '[{"key":"latest","label":"최신"},{"key":"diplomacy","label":"외교"},{"key":"economy","label":"경제"},{"key":"special","label":"특집"},{"key":"archive","label":"과거 특집"}]'
WHERE `key` = 'menu_tabs';

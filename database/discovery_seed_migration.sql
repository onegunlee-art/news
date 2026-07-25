-- Discovery seed flag migration (discovery_* only)

SET @has_is_seed := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'discovery_editions'
      AND COLUMN_NAME = 'is_seed'
);
SET @sql_add_seed := IF(
    @has_is_seed = 0,
    'ALTER TABLE `discovery_editions` ADD COLUMN `is_seed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `change_count`, ADD KEY `idx_discovery_edition_seed` (`is_seed`, `edition_date`)',
    'SELECT 1'
);
PREPARE stmt_add_seed FROM @sql_add_seed;
EXECUTE stmt_add_seed;
DEALLOCATE PREPARE stmt_add_seed;

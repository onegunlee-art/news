-- Judgment Moat: Lesson Cards + Prediction calibration (Admin-only)

CREATE TABLE IF NOT EXISTS `judgment_lessons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule` TEXT NOT NULL COMMENT 'Transferable house rule (not raw edit text)',
  `error_type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `scqa_section` VARCHAR(100) NOT NULL DEFAULT '*',
  `topic_category` VARCHAR(50) NOT NULL DEFAULT '*',
  `polarity` ENUM('tighten', 'loosen', 'neutral') NOT NULL DEFAULT 'tighten',
  `evidence_json` JSON NULL COMMENT 'before/after/report_id snapshot',
  `frequency` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('rag', 'promoted') NOT NULL DEFAULT 'rag',
  `source_report_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jl_section_topic` (`scqa_section`, `topic_category`, `status`),
  KEY `idx_jl_error_type` (`error_type`),
  KEY `idx_jl_frequency` (`frequency` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prediction_outcomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `report_week` VARCHAR(10) NOT NULL,
  `scenario_type` ENUM('base', 'upside', 'downside') NOT NULL DEFAULT 'base',
  `scenario_index` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `prediction_signal` TEXT NULL,
  `probability` TINYINT UNSIGNED NULL,
  `outcome_status` ENUM('pending', 'hit', 'miss', 'partial') NOT NULL DEFAULT 'pending',
  `outcome_notes` TEXT NULL,
  `scored_at` DATETIME NULL,
  `scored_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_po_report` (`report_id`),
  KEY `idx_po_week` (`report_week`),
  KEY `idx_po_status` (`outcome_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

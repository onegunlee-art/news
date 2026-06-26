-- 시리즈 표지 메타데이터 테이블: 과거 특집 매거진 표지용
-- 표지 텍스트, 색상, 크기, 위치 등 관리

CREATE TABLE IF NOT EXISTS `series_covers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `series_id` VARCHAR(36) NOT NULL UNIQUE COMMENT '시리즈 그룹 ID (news.series_id와 연결)',
  `cover_text` VARCHAR(100) NULL COMMENT '표지에 표시할 텍스트',
  `text_color` VARCHAR(20) DEFAULT '#ffffff' COMMENT '텍스트 색상 (hex)',
  `text_size` INT DEFAULT 24 COMMENT '텍스트 크기 (px)',
  `text_x` INT DEFAULT 50 COMMENT '텍스트 X 위치 (% 단위, 0~100)',
  `text_y` INT DEFAULT 50 COMMENT '텍스트 Y 위치 (% 단위, 0~100)',
  `is_featured` TINYINT(1) DEFAULT 0 COMMENT '과거 특집에 노출 여부',
  `display_order` INT DEFAULT 0 COMMENT '정렬 순서 (낮을수록 먼저)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_series_covers_featured` (`is_featured`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

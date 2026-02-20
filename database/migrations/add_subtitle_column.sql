-- subtitle 컬럼 추가 (Foreign Affairs 등 매체의 부제목)
ALTER TABLE news ADD COLUMN subtitle VARCHAR(500) NULL COMMENT '부제목' AFTER title;

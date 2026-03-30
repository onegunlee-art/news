-- 취소 요청: 환불 준비(취소 처리) 단계 — 완결 전에만 설정 가능, 취소 처리 취소로 되돌림
ALTER TABLE `cancel_requests`
    ADD COLUMN `prepared_at` TIMESTAMP NULL DEFAULT NULL COMMENT '환불 준비(취소 처리) 확정 시각' AFTER `message`;

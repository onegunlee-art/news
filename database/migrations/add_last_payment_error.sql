-- 결제 실패 사유를 저장하여 에러 페이지에서 조회할 수 있도록 함
ALTER TABLE users
  ADD COLUMN last_payment_error TEXT NULL DEFAULT NULL AFTER steppay_order_code,
  ADD COLUMN last_payment_error_at DATETIME NULL DEFAULT NULL AFTER last_payment_error;

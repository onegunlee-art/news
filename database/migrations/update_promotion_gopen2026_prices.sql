-- GOPEN2026 프로모션 코드: 변경된 가격·price_code 반영 (2026-04)
-- plan_price_map JSON을 새로운 정가/할인가 기준으로 업데이트
--
-- 정가 기준 (config/app.php 동기)
--   1m  ₩11,000  →  프로모 20% → ₩8,800   price_2MDFcYLy7
--   3m  ₩26,400  →  프로모 20% → ₩21,120  price_dCQOmyrM2
--   6m  ₩46,200  →  프로모 20% → ₩36,960  price_Nkkw9S2TW
--  12m  ₩79,200  →  프로모 20% → ₩63,360  price_bRAjyZadH

UPDATE `promotion_codes`
SET `plan_price_map` = JSON_OBJECT(
    '1m',  JSON_OBJECT('price_code', 'price_2MDFcYLy7', 'amount', 8800),
    '3m',  JSON_OBJECT('price_code', 'price_dCQOmyrM2', 'amount', 21120),
    '6m',  JSON_OBJECT('price_code', 'price_Nkkw9S2TW', 'amount', 36960),
    '12m', JSON_OBJECT('price_code', 'price_bRAjyZadH', 'amount', 63360)
),
    `updated_at` = NOW()
WHERE UPPER(`code`) = 'GOPEN2026';

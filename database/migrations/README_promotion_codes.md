# 프로모션 코드 (구독 결제)

1. MySQL에서 `create_promotion_codes.sql` 실행 (테이블 2개 + `users` 컬럼 2개).
2. StepPay 콘솔에서 할인가용 `price`를 플랜별로 만들고 `price_code`를 확보합니다.
3. 어드민 **프로모션 코드** 탭에서 `plan_price_map` JSON으로 플랜 키(`1m`, `3m` 등)와 매핑합니다.

`users`에 `pending_checkout_plan_id` / `pending_promotion_code_id` 컬럼이 이미 있으면 ALTER 구문은 에러나므로 해당 줄만 생략하세요.

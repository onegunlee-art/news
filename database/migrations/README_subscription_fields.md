# 구독(StepPay) 필드 마이그레이션 설명

## 개요

`users` 테이블에 **StepPay 구독 결제** 연동을 위해 필요한 컬럼 5개를 추가하는 마이그레이션입니다.  
이미 `users` 테이블이 있고, `status` 컬럼 다음에 구독 관련 컬럼이 없을 때 **한 번만** 실행하면 됩니다.

---

## 추가되는 컬럼

| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| `is_subscribed` | TINYINT(1), 기본값 0 | 구독 여부 (0: 미구독, 1: 구독 중) |
| `subscription_expires_at` | TIMESTAMP NULL | 구독 만료일 (NULL이면 미구독 또는 만료일 없음) |
| `steppay_customer_id` | BIGINT NULL | StepPay에서 발급한 고객 ID (주문/구독 생성 시 사용) |
| `steppay_subscription_id` | VARCHAR(100) NULL | StepPay 구독 ID (웹훅으로 구독 상태 동기화 시 사용) |
| `steppay_order_code` | VARCHAR(100) NULL | StepPay 주문 코드 (결제 URL 생성 등에 사용) |

- **삽입 위치**: 기존 `status` 컬럼 바로 다음.
- **기본값**: `is_subscribed`만 0, 나머지는 NULL.
- **이미 컬럼이 있으면**: 아래 SQL은 `ADD COLUMN`이라 컬럼이 이미 있으면 에러가 납니다. 그럴 때는 이 마이그레이션을 실행하지 않으면 됩니다.

---

## 실행 방법

1. MySQL/MariaDB에 접속 (운영 DB 또는 로컬 DB).
2. 사용 중인 DB 선택: `USE your_database_name;`
3. 아래 **복사용 SQL** 블록 전체를 복사해 쿼리 실행.

---

## 복사용 SQL

```sql
-- ============================================================
-- users 테이블에 구독(StepPay) 관련 컬럼 추가
-- 실행: 운영 DB에서 한 번 실행 (컬럼이 이미 있으면 실행하지 말 것)
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `is_subscribed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '구독 여부' AFTER `status`,
  ADD COLUMN `subscription_expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT '구독 만료일' AFTER `is_subscribed`,
  ADD COLUMN `steppay_customer_id` BIGINT NULL DEFAULT NULL COMMENT 'StepPay 고객 ID' AFTER `subscription_expires_at`,
  ADD COLUMN `steppay_subscription_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'StepPay 구독 ID' AFTER `steppay_customer_id`,
  ADD COLUMN `steppay_order_code` VARCHAR(100) NULL DEFAULT NULL COMMENT 'StepPay 주문 코드' AFTER `steppay_subscription_id`;
```

---

## 실행 후 확인

```sql
DESCRIBE users;
```

`status` 다음에 `is_subscribed`, `subscription_expires_at`, `steppay_customer_id`, `steppay_subscription_id`, `steppay_order_code` 가 순서대로 보이면 적용된 것입니다.

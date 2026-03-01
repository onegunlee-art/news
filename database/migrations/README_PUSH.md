# 푸시 알림 DB 마이그레이션

## 실행 방법

1. MySQL 클라이언트 또는 phpMyAdmin에서 `ailand` DB 선택
2. `add_push_subscriptions.sql` 파일의 SQL을 복사해 실행

```sql
-- 또는 직접 실행:
SOURCE /path/to/database/migrations/add_push_subscriptions.sql;
```

## 확인

```sql
SHOW TABLES LIKE 'push_subscriptions';
DESCRIBE push_subscriptions;
```

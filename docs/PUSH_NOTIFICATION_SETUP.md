# 푸시 알림 설정 가이드

마이 페이지의 **새 글 푸시 알림** 기능이 동작하려면 아래 작업을 **한 번만** 진행해주세요.

---

## 1. DB 마이그레이션 실행

`push_subscriptions` 테이블을 생성합니다.

**방법 A: phpMyAdmin**
1. phpMyAdmin에서 `ailand` DB 선택
2. SQL 탭 열기
3. `database/migrations/add_push_subscriptions.sql` 파일 내용을 붙여넣고 실행

**방법 B: MySQL 클라이언트**
```bash
mysql -u ailand -p ailand < database/migrations/add_push_subscriptions.sql
```

**확인**
```sql
SHOW TABLES LIKE 'push_subscriptions';
DESCRIBE push_subscriptions;
```

---

## 2. VAPID 키 생성 및 설정

Web Push용 VAPID 키를 생성하고 설정합니다.

**1) 키 생성**
```bash
php scripts/generate-vapid-keys.php
```

**2) 출력 예시**
```
=== VAPID 키 생성 완료 ===
publicKey:  BNxxx...
privateKey: xxx...
```

**3) 설정 파일 생성**
- `config/vapid.example.php`를 복사하여 `config/vapid.php` 생성
- 출력된 `publicKey`, `privateKey` 값을 붙여넣기

```php
<?php
return [
    'publicKey'  => '여기에_출력된_공개키_붙여넣기',
    'privateKey' => '여기에_출력된_비공개키_붙여넣기',
];
```

> ⚠️ `config/vapid.php`는 `.gitignore`에 추가해 비공개로 관리하세요.

---

## 3. 동작 확인

1. **HTTPS** 또는 **localhost** 환경에서만 푸시 알림이 동작합니다.
2. 로그인 후 **마이 페이지** → **알림 종 아이콘** 클릭 → **새 글 푸시 알림** 토글 ON
3. Admin에서 **뉴스 게시** 또는 **임시저장 → 뉴스 저장** 시 구독자에게 푸시가 발송됩니다.

---

## 정리: 사용자가 직접 해야 할 작업

| # | 작업 | 명령/경로 |
|---|------|----------|
| 1 | DB 마이그레이션 | `database/migrations/add_push_subscriptions.sql` 실행 |
| 2 | VAPID 키 생성 | `php scripts/generate-vapid-keys.php` |
| 3 | VAPID 설정 파일 생성 | `config/vapid.php` 생성 후 키 붙여넣기 |

나머지(프론트/백엔드 코드, sw.js, API, Admin 트리거 등)는 이미 구현되어 있습니다.

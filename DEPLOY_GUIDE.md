# 🚀 dothome 배포 가이드

## 📋 호스팅 정보

| 항목 | 값 |
|------|-----|
| 도메인 | ailand.dothome.co.kr |
| FTP 서버 | ftp.dothome.co.kr |
| FTP 아이디 | ailand |
| 웹루트 | /hosting/ailand/html |
| PHP 버전 | 8.4 |
| MySQL | 8.0 |
| DB 명 | ailand |
| DB 아이디 | ailand |

---

## 📁 배포할 파일 구조

```
/hosting/ailand/
├── html/                    ← public 폴더 내용
│   ├── index.php
│   ├── index.html
│   ├── .htaccess
│   ├── favicon.svg
│   ├── test_connection.php
│   └── assets/
│       ├── index--21QYBhR.css
│       ├── index-BIS3cbwC.js
│       ├── ui-B9_z7_7O.js
│       └── vendor-Det8oHYH.js
├── src/                     ← src 폴더 전체
│   └── backend/
│       ├── Controllers/
│       ├── Core/
│       ├── Services/
│       └── ...
├── config/                  ← config 폴더 전체
│   ├── app.php
│   ├── database.php
│   ├── kakao.php
│   ├── naver.php
│   └── routes.php
└── storage/
    ├── cache/
    └── logs/
```

---

## 🔧 배포 방법

### 방법 1: FileZilla 사용 (권장)

1. **FileZilla 다운로드**: https://filezilla-project.org/

2. **FTP 연결 설정**:
   - 호스트: `ftp.dothome.co.kr`
   - 사용자명: `ailand`
   - 비밀번호: (dothome에서 설정한 FTP 비밀번호)
   - 포트: `21`

3. **파일 업로드**:
   - `public/` 폴더 내용 → `/html/`로 업로드
   - `src/` 폴더 → `/src/`로 업로드
   - `config/` 폴더 → `/config/`로 업로드
   - `storage/` 폴더 → `/storage/`로 업로드

### 방법 2: dothome 파일관리자 사용

1. https://www.dothome.co.kr 로그인
2. 마이닷홈 → 호스팅 관리
3. ailand 호스팅 선택 → 파일관리자
4. 파일 업로드

### 방법 3: PowerShell 스크립트

```powershell
# 프로젝트 폴더에서 실행
.\scripts\deploy-ftp.ps1
```

---

## ⚙️ 배포 후 설정

### 1. DB 비밀번호 설정

`config/database.php` 파일에서 비밀번호 설정:

```php
'password' => getenv('DB_PASSWORD') ?: 'YOUR_DB_PASSWORD',
```

### 2. 데이터베이스 테이블 생성

1. https://ailand.dothome.co.kr/myadmin 접속
2. `database/schema.sql` 파일 내용 실행

### 3. API 키 설정

**카카오 API** (`config/kakao.php`):
```php
'client_id' => 'YOUR_KAKAO_REST_API_KEY',
'client_secret' => 'YOUR_KAKAO_CLIENT_SECRET',
```

**네이버 API** (`config/naver.php`):
```php
'client_id' => 'YOUR_NAVER_CLIENT_ID',
'client_secret' => 'YOUR_NAVER_CLIENT_SECRET',
```

---

## ✅ 배포 확인

배포 완료 후 다음 URL에서 확인:

- 🌐 **메인 페이지**: https://ailand.dothome.co.kr
- 🔧 **서버 상태**: https://ailand.dothome.co.kr/test_connection.php
- 📊 **API 확인**: https://ailand.dothome.co.kr/api
- 🗄️ **DB 관리**: https://ailand.dothome.co.kr/myadmin

---

## 🔐 보안 체크리스트

- [ ] FTP 비밀번호 강력하게 설정
- [ ] DB 비밀번호 설정 완료
- [ ] `config/` 폴더 직접 접근 차단 확인
- [ ] 에러 로그 확인 (storage/logs/)
- [ ] HTTPS 적용 확인

---

## 🆘 문제 해결

### 500 에러 발생 시
1. `.htaccess` 파일 확인
2. PHP 에러 로그 확인: `/hosting/ailand/html/../storage/logs/`
3. 파일 권한 확인 (755 for folders, 644 for files)

### DB 연결 실패 시
1. `config/database.php` 비밀번호 확인
2. phpMyAdmin에서 DB 접속 테스트
3. 호스트가 `localhost`인지 확인

### 빈 페이지 표시 시
1. `index.html` 파일 업로드 확인
2. `assets/` 폴더 업로드 확인
3. 브라우저 캐시 삭제 후 새로고침

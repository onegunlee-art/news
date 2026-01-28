# News 맥락 분석 - 로컬 개발 환경 설치 가이드

## 1. Node.js 설치 (필수)

### 자동 설치 (관리자 PowerShell)
```powershell
# 관리자 권한 PowerShell에서 실행
winget install OpenJS.NodeJS.LTS
```

### 수동 설치
1. https://nodejs.org 방문
2. **LTS** 버전 다운로드 (권장: 20.x)
3. 설치 파일 실행
4. 설치 완료 후 **PowerShell 재시작**

### 설치 확인
```powershell
node --version   # v20.x.x
npm --version    # 10.x.x
```

---

## 2. PHP 설치 (선택 - 백엔드 테스트용)

### 옵션 A: XAMPP (초보자 권장)
1. https://www.apachefriends.org 방문
2. Windows용 다운로드 및 설치
3. PHP 경로: `C:\xampp\php\php.exe`

### 옵션 B: Laragon (개발자 권장)
1. https://laragon.org 방문
2. 다운로드 및 설치
3. PHP 자동 포함

### 옵션 C: PHP 단독 설치
1. https://windows.php.net/download 방문
2. **VS17 x64 Thread Safe** 다운로드
3. `C:\php`에 압축 해제
4. 환경 변수 PATH에 `C:\php` 추가

---

## 3. 프로젝트 설정

### PowerShell에서 실행:
```powershell
# 프로젝트 폴더로 이동
cd "C:\Users\IBK\OneDrive\바탕 화면\News"

# 설정 스크립트 실행
.\scripts\setup.ps1

# 서버 실행
.\scripts\start.ps1
```

---

## 4. 서버 실행

### 프론트엔드만 (React)
```powershell
cd src\frontend
npm run dev
```
→ http://localhost:5173

### 백엔드만 (PHP)
```powershell
cd public
php -S localhost:8000
```
→ http://localhost:8000

### 둘 다 (권장)
```powershell
.\scripts\start.ps1
# 선택: 3
```

---

## 5. 브라우저 테스트

1. http://localhost:5173 접속
2. 메인 페이지 확인
3. 카카오 로그인 테스트 (API 키 설정 필요)
4. 뉴스 검색 테스트 (네이버 API 키 설정 필요)

---

## 문제 해결

### "node를 찾을 수 없습니다"
- PowerShell 재시작 필요
- 또는 직접 경로 사용: `& "C:\Program Files\nodejs\node.exe" --version`

### "php를 찾을 수 없습니다"
- XAMPP/Laragon 설치 또는 PHP 단독 설치
- PATH 환경 변수 확인

### npm install 오류
```powershell
npm cache clean --force
rm -r node_modules
npm install
```

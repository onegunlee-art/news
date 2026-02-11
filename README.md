# News 맥락 분석 홈페이지

**공식 사이트**: **https://www.thegist.co.kr**

Gisters, Becoming Leaders - 키워드 추출, 감정 분석, 맥락 요약

## 🚀 주요 기능

- **뉴스 검색**: NYT 뉴스 API 연동
- **키워드 추출**: 형태소 분석 기반 핵심 키워드 추출
- **감정 분석**: 긍정/부정/중립 감정 분류
- **맥락 요약**: AI 기반 텍스트 요약
- **카카오 로그인**: OAuth 2.0 소셜 로그인
- **북마크**: 관심 뉴스 저장

## 🛠 기술 스택

### 프론트엔드
- React 18 + TypeScript
- React Router v6
- Tailwind CSS
- Framer Motion
- Zustand (상태 관리)
- Vite (빌드 도구)

### 백엔드
- PHP 8.4 (OOP 기반)
- MySQL 8.0
- PDO (Prepared Statements)
- JWT 인증
- REST API

### 인프라
- dothome 호스팅
- GitHub Actions CI/CD
- FTP 자동 배포

## 📁 프로젝트 구조

```
/
├── public/                # 웹루트 (dothome 배포용)
│   ├── index.php         # API 진입점
│   └── .htaccess         # Apache 설정
├── src/
│   ├── frontend/         # React SPA
│   │   ├── src/
│   │   │   ├── components/
│   │   │   ├── pages/
│   │   │   ├── services/
│   │   │   └── store/
│   │   └── package.json
│   └── backend/          # PHP API
│       ├── Core/         # 프레임워크 핵심
│       ├── Controllers/  # API 컨트롤러
│       ├── Services/     # 비즈니스 로직
│       ├── Repositories/ # 데이터 접근
│       ├── Models/       # 도메인 모델
│       ├── Middleware/   # 미들웨어
│       └── Utils/        # 유틸리티
├── config/               # 설정 파일
├── database/             # DB 스키마
├── .github/workflows/    # CI/CD
└── storage/              # 캐시/로그
```

## 🔧 설치 방법

### 1. 환경 변수 설정
```bash
cp env.example .env
# .env 파일을 열어 실제 값 입력
```

### 2. 프론트엔드 빌드
```bash
cd src/frontend
npm install
npm run build
```

### 3. 데이터베이스 설정
```bash
# MySQL에서 database/schema.sql 실행
mysql -u ailand -p ailand < database/schema.sql
```

### 4. dothome 배포
```bash
# GitHub main 브랜치에 push하면 자동 배포
# 또는 수동으로 public/, src/backend/, config/ 업로드
```

## 🔑 API 키 발급

### 카카오 로그인
1. [Kakao Developers](https://developers.kakao.com) 접속
2. 애플리케이션 생성
3. REST API 키 발급
4. Redirect URI 등록: `http://your-domain.com/api/auth/kakao/callback`

## 📡 API 엔드포인트

| 메서드 | 경로 | 설명 |
|--------|------|------|
| GET | /api/health | 서버 상태 확인 |
| GET | /api/auth/kakao | 카카오 로그인 |
| GET | /api/auth/kakao/callback | 카카오 콜백 |
| POST | /api/auth/refresh | 토큰 갱신 |
| POST | /api/auth/logout | 로그아웃 |
| GET | /api/news | 뉴스 목록 |
| GET | /api/news/search | 뉴스 검색 |
| GET | /api/news/:id | 뉴스 상세 |
| POST | /api/analysis/news/:id | 뉴스 분석 |
| POST | /api/analysis/text | 텍스트 분석 |

## 🔒 보안

- JWT 토큰 기반 인증
- Prepared Statements (SQL Injection 방지)
- XSS 방지 헤더
- CORS 설정
- Rate Limiting
- HTTPS 강제 (프로덕션)

## 📝 라이센스

MIT License

## 👥 기여

이슈와 PR을 환영합니다!

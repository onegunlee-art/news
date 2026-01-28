# 카카오 로그인 연동 가이드

## ✅ 현재 설정 상태

- **REST API 키**: `2b4a37bb18a276469b69bf3d8627e425` (설정 완료)
- **리다이렉트 URI**: `http://ailand.dothome.co.kr/api/auth/kakao/callback`
- **백엔드 코드**: 구현 완료
- **프론트엔드 코드**: 구현 완료

## 🔧 카카오 개발자 콘솔 설정

### 1. 리다이렉트 URI 등록 (필수)

1. [Kakao Developers](https://developers.kakao.com) 접속
2. 내 애플리케이션 선택
3. **앱 설정** → **플랫폼** 메뉴로 이동
4. **Web 플랫폼 등록** 클릭
5. 사이트 도메인 등록: `http://ailand.dothome.co.kr`
6. **제품 설정** → **카카오 로그인** → **활성화 설정** ON
7. **Redirect URI 등록**:
   ```
   http://ailand.dothome.co.kr/api/auth/kakao/callback
   ```

### 2. 동의 항목 설정

**제품 설정** → **카카오 로그인** → **동의항목**에서 다음 항목 활성화:

- ✅ 닉네임 (필수)
- ✅ 프로필 사진 (선택)
- ✅ 카카오계정(이메일) (선택)

### 3. 테스트 사용자 추가 (개발 단계)

**제품 설정** → **카카오 로그인** → **카카오 로그인 활성화 설정**:
- **테스트 앱**으로 설정된 경우, 테스트 사용자 추가 필요

## 🧪 테스트 방법

### 1. 로컬 테스트

```bash
# 프론트엔드 개발 서버 실행
cd src/frontend
npm run dev

# 브라우저에서 접속
http://localhost:5173/login
```

### 2. 카카오 로그인 테스트 페이지

```
http://ailand.dothome.co.kr/api/auth/test.php
```

이 페이지에서 카카오 API 설정 상태를 확인할 수 있습니다.

### 3. 실제 로그인 플로우 테스트

1. 프론트엔드에서 "카카오로 시작하기" 버튼 클릭
2. 카카오 로그인 페이지로 리다이렉트
3. 카카오 계정으로 로그인
4. 권한 동의
5. 자동으로 앱으로 리다이렉트되어 로그인 완료

## 📋 로그인 플로우

```
1. 사용자 클릭: "카카오로 시작하기"
   ↓
2. GET /api/auth/kakao
   → 카카오 인가 페이지로 리다이렉트
   ↓
3. 사용자가 카카오 로그인 및 동의
   ↓
4. GET /api/auth/kakao/callback?code=xxx&state=xxx
   → 인가 코드로 액세스 토큰 발급
   → 사용자 정보 조회
   → DB에 사용자 생성/업데이트
   → JWT 토큰 발급
   ↓
5. 프론트엔드 /auth/callback으로 리다이렉트
   → 토큰 저장
   → 홈으로 이동
```

## 🔍 문제 해결

### 오류: "redirect_uri_mismatch"

**원인**: 카카오 개발자 콘솔에 리다이렉트 URI가 등록되지 않음

**해결**:
1. 카카오 개발자 콘솔 접속
2. 제품 설정 → 카카오 로그인 → Redirect URI 등록
3. 정확히 `http://ailand.dothome.co.kr/api/auth/kakao/callback` 입력

### 오류: "invalid_client"

**원인**: REST API 키가 잘못되었거나 설정되지 않음

**해결**:
1. `config/kakao.php` 파일 확인
2. REST API 키가 올바른지 확인
3. 카카오 개발자 콘솔에서 키 재발급 후 업데이트

### 오류: "insufficient_scope"

**원인**: 필요한 동의 항목이 활성화되지 않음

**해결**:
1. 카카오 개발자 콘솔 → 제품 설정 → 카카오 로그인 → 동의항목
2. 닉네임, 프로필 사진, 이메일 항목 활성화

## 📝 참고 문서

- [카카오 로그인 REST API 가이드](https://developers.kakao.com/docs/latest/ko/kakaologin/rest-api)
- [카카오 로그인 동의항목 가이드](https://developers.kakao.com/docs/latest/ko/kakaologin/prerequisite)

## ✅ 체크리스트

- [x] REST API 키 설정 완료
- [ ] 카카오 개발자 콘솔에 리다이렉트 URI 등록
- [ ] 동의 항목 설정 완료
- [ ] 로그인 플로우 테스트 완료
- [ ] 사용자 정보 저장 확인
- [ ] JWT 토큰 발급 확인

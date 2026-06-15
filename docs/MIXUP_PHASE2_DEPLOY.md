# Mix-up Phase 2 배포 체크리스트

## 1. 사전 조건

- 브랜치: `feature/mixup-stance`
- Supabase: `Q-IRAN-FOREVER-001` 수동 INSERT 완료 (`database/migrations/edu_iran_quest_manual.sql`)
- 환경변수 (서버 `.env`):

```env
EDU_LLM_PROVIDER=openai
EDU_OPENAI_MODEL=gpt-5.4
EDU_OPENAI_FAST_MODEL=gpt-5.4-mini
OPENAI_API_KEY=...
EDU_MIXUP_RAG=1
```

## 2. 배포 명령 (서버)

```bash
cd /var/www/thegist
git fetch origin
git checkout feature/mixup-stance
git pull origin feature/mixup-stance

# PHP 문법 확인
php -l public/api/edu/session/chat.php
php -l public/api/edu/session/turn.php
php -l src/backend/Services/edu/Agents/Hammer.php
```

## 3. 배포 후 검증

### 3.1 Health check

```bash
curl -s https://www.thegist.co.kr/api/edu/health.php | jq .
```

기대: `llm_provider: openai`, `edu_mixup_rag: true`

### 3.2 Hammer 격리 테스트 (7/7)

```bash
php tools/edu_hammer_convergent_test.php --live
```

### 3.3 E2E 격리 테스트

```bash
php tools/edu_convergent_e2e_test.php --live
```

### 3.4 Factory 추출 (서버, pdo_mysql 있음)

```bash
php tools/edu_quest_factory_convergent_test.php --arc=ARC-IRAN-REGION
# 또는 MySQL 없이 mock:
php tools/edu_quest_factory_mock_test.php --live
```

### 3.5 라이브 세션 E2E (수동)

1. `Q-IRAN-FOREVER-001` 퀘스트로 학생 세션 시작
2. commit 단계에서 tech/politics/structure 근거 각각 입력
3. hammer 응답 확인:
   - `hammer_mode: convergent` (명확 입력)
   - `hammer_mode: convergent_meta_ask` (모호 입력)
   - `mixup_sources: []` (RAG 미호출)
4. 기존 adversarial 퀘스트는 기존 동작 유지 확인

## 4. 롤백

```bash
git checkout main
# 또는
EDU_LLM_PROVIDER=anthropic
```

기존 퀘스트(`hammer_hints.mode` 없음)는 adversarial 로직 그대로 동작.

## 5. 변경 파일 요약

| 파일 | 변경 |
|------|------|
| `Hammer.php` | 수렴형 strikeConvergent, 층위 분류, meta_ask |
| `EduQuestFactory.php` | extractConvergentAxes, buildConvergentQuest |
| `eduQuest.php` | eduBuildMixupContext, convergent hammer payload |
| `chat.php`, `turn.php` | convergent 시 RAG 스킵 |
| `_llm_openai.php` | OpenAI 클라이언트 |

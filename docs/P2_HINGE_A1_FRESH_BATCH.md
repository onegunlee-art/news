# P2-A1 Fresh Batch — 새 글 5편 추출·검수

> **목적:** 630(익숙한 글) 외 **처음 보는 글**에서 추출 안정성 + confidence 게이트 검증  
> **입력:** MySQL `news.content` only · judgement 금지  
> **안 함:** A2 매핑, 퀘스트 시드, 라이브

---

## 제외 ID

| 구분 | news_id |
|------|---------|
| P2-H 정답지 | 631, 555, 618, 546, 570, 621 |
| A1 완주 | 630 |
| Part B / 대화 | 528, 452, 514, 459, 507 |
| 630 context | 475, 449, 615 |

---

## 선정 5편 (카테고리 분산)

| ID | category | 제목 | 선정 이유 |
|----|----------|------|-----------|
| **196** | security / politics | 이란 핵 프로그램에 대한 어중간한 솔루션 3가지 | 정치·안보, 경첩(A vs B) 뚜렷 |
| **150** | economy | 전기세 폭등의 주범, AI데이터센터!? | 경제, 통념 vs 복잡한 인과 |
| **371** | science_technology | '자율형' AI 에이전트의 등장과 위협 | 과학/기술 |
| **288** | society | 청소년 AI 사용에 대한 바람직한 관리 방안 | 사회 |
| **220** | economy / geopolitics | 유가 급등의 파급효과 | 경제·공급망, 630과 다른 축 |

`news.content` 없으면 EC2에서 `edu_check_news_snapshot_sources.php`로 확인 후 대체.

---

## EC2 — 0) logs 권한 (한 번)

```bash
cd /var/www/thegist

sudo mkdir -p docs/hinge_extractions docs/hinge_reviews storage/logs
sudo chown -R ubuntu:ubuntu docs/hinge_extractions docs/hinge_reviews
sudo chown ubuntu:www-data storage/logs
sudo chmod 775 storage/logs
sudo touch storage/logs/edu_llm_count_$(date +%Y-%m-%d).txt
sudo chown ubuntu:www-data storage/logs/edu_llm_count_*.txt
```

---

## EC2 — 1) 추출 (5편)

```bash
cd /var/www/thegist

for id in 196 150 371 288 220; do
  echo "========== news_id=$id =========="
  php tools/edu_gist_hinge_extract.php "$id"
  echo
done
```

각 편 stdout에서 **confidence**, **needs_review**, **hinge** 확인.

---

## EC2 — 2) 본인 검수 (편마다 1회)

**각 JSON을 읽고** 승인 또는 수정. `high`인데 틀리면 **반드시 edit**.

```bash
cd /var/www/thegist

# 예: 196 — 맞으면 approve
cat docs/hinge_extractions/196.json
php tools/edu_hinge_review.php approve 196 --reviewer=iwg

# 예: 틀리면 edit (필드만 바꿔도 됨)
# php tools/edu_hinge_review.php edit 196 --hinge="..." --side_b="..."

cat docs/hinge_extractions/150.json
php tools/edu_hinge_review.php approve 150 --reviewer=iwg

cat docs/hinge_extractions/371.json
php tools/edu_hinge_review.php approve 371 --reviewer=iwg

cat docs/hinge_extractions/288.json
php tools/edu_hinge_review.php approve 288 --reviewer=iwg

cat docs/hinge_extractions/220.json
php tools/edu_hinge_review.php approve 220 --reviewer=iwg
```

`approve` / `edit`는 **본인 판정에 맞게** 바꿔서 실행.

---

## EC2 — 3) 집계

```bash
cd /var/www/thegist

php tools/edu_hinge_gate_stats.php --write-md

php tools/edu_hinge_batch_report.php 196 150 371 288 220 --write-md

cat docs/hinge_reviews/gate_stats.md
cat docs/P2_HINGE_A1_FRESH_RESULT.md
```

---

## 검수 시 체크리스트

1. **과신:** `confidence=high` 인데 수정(edit)한 편이 있나 → 있으면 게이트 전제 흔들림  
2. **low/null:** 어떤 글에서 뜨나 — 통념 약한 글(514류) vs 단순 뉴스 vs 무작위  
3. **경첩:** A이지만 B 긴장이 본문과 맞나, shake에 fact 있나  

---

## 통과 기준 (A2 진입)

- 승인률 **4/5+** (수정 1편 이하)  
- **false_pass (high+edit) = 0** (또는 1편 이하 + 원인 명확)  
- low/null은 **진짜 애매한 글**에만 — 무작위 low면 프롬프트 보강 먼저  

결과 나오면 `docs/P2_HINGE_A1_FRESH_RESULT.md` 공유.

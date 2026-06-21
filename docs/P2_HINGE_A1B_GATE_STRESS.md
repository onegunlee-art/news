# P2-A1b Gate Stress — 514 · 528

> **목적:** confidence **거르기** 검증 (P2-A1 easy batch는 전원 high → 미검)  
> **입력:** MySQL `news.content` only · judgement 금지  
> **안 함:** A2, 퀘스트, 라이브

---

## 왜 이 두 편?

| ID | P2 덤프 유형 | 게이트 기대 |
|----|--------------|-------------|
| **514** | 반론·한계 **애매** — 통념·반박 한 문장에 엉킴 | `low`/`medium` 또는 `high`+본인 **edit** |
| **528** | **통념 약**·틀 적용형 (베트남/한국 전쟁 비유) | `low`/`medium` |

**가능성 A (게이트 OK):** 애매 편에서 low/null → 사람 검수 큐  
**가능성 B (과신):** 애매한데도 high → 본인 edit → false_pass

---

## 제외 (이미 검수·추출됨)

631, 555, 618, 546, 570, 621, 630, 196, 150, 371, 288, 220, 452, 459, 507 + Part B 선행

---

## EC2 — 0) 권한 (한 번, 이미 했으면 skip)

```bash
cd /var/www/thegist
sudo mkdir -p docs/hinge_extractions docs/hinge_reviews storage/logs
sudo chown -R ubuntu:ubuntu docs/hinge_extractions docs/hinge_reviews
sudo chown ubuntu:www-data storage/logs
sudo chmod 775 storage/logs
```

---

## EC2 — 1) 추출

```bash
cd /var/www/thegist

for id in 514 528; do
  echo "========== news_id=$id =========="
  php tools/edu_gist_hinge_extract.php "$id"
  echo
done
```

**stdout에서 반드시 확인:**

- `confidence` (high / medium / **low** / null)
- `[검증 필요]` 플래그 (`needs_review: true`?)
- `hinge` 한 줄이 본문 긴장과 맞는지

---

## EC2 — 2) 본인 검수 (편마다)

**high라도 애매하면 edit — 과신 잡는 게 이번 시험의 핵심.**

```bash
cd /var/www/thegist

cat docs/hinge_extractions/514.json
# 맞으면:
php tools/edu_hinge_review.php approve 514 --reviewer=iwg
# 틀리면:
# php tools/edu_hinge_review.php edit 514 --hinge="..." --side_a="..." --side_b="..."

cat docs/hinge_extractions/528.json
php tools/edu_hinge_review.php approve 528 --reviewer=iwg
# 또는 edit
```

### 검수 체크 (514)

- side_a가 **통념/표면**인가, 아니면 본문 한 문장을 그대로 가져왔나?
- hinge가 “성과 vs 한계” **한 긴장**인가, 두 주제를 엮은 **헛것**인가?
- shake에 **미중 회담·대만** 등 본문 fact?

### 검수 체크 (528)

- side_a: “전쟁은 X처럼 끝난다” **틀**인가, 없는 통념 invent?
- 통념이 약한 글 — **low**가 떠야 정상(가능성 A)?
- high면 **특히 꼼꼼히** — edit면 **false_pass(과신)**

---

## EC2 — 3) 집계 (easy 6편 + stress 2편)

```bash
cd /var/www/thegist

php tools/edu_hinge_gate_stats.php --write-md

php tools/edu_hinge_batch_report.php 514 528 --write-md

php tools/edu_hinge_batch_report.php 630 196 150 371 288 220 514 528 --write-md

cat docs/hinge_reviews/gate_stats.md
cat docs/hinge_reviews/P2_HINGE_A1_FRESH_RESULT.md
```

(`batch_report --write-md`는 `docs/hinge_reviews/` 아래 저장 — 배포 `38e3983`+ 또는 `sudo chown ubuntu:ubuntu docs`)

---

## 통과 해석

| 결과 | 판정 |
|------|------|
| 514 또는 528 **low/null** + 본인 승인/수정 | **가능성 A** — 게이트가 애매함을 표시 |
| 514·528 **high** + 본인 **approve** | 추출 OK, 게이트 **선별력 추가 미약** |
| 514·528 **high** + 본인 **edit** | **가능성 B** — 과신, high도 전수 검수 필요 |
| 추출 자체 ✗ (헛 hinge) | 프롬프트 보강 **A2 전** |

결과 붙여주면 A1b 총평 + A2 착수 여부 같이 정리.

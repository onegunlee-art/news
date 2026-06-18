-- ============================================================
-- GIST EDU — 이란 퀘스트 수동 INSERT (Phase 2a 테스트용)
-- Supabase SQL Editor에서 직접 실행
-- ============================================================

-- 1. edu_daily_quests에 이란 수렴형 퀘스트 INSERT
INSERT INTO edu_daily_quests (
  quest_code,
  quest_title,
  grade_band,
  status,
  manual_arc,
  pro_line,
  con_line,
  alignment_summary,
  conflict_summary,
  hammer_hints,
  pilot_priority
) VALUES (
  'Q-IRAN-FOREVER-001',
  '이란 전쟁, 정말 끝낼 수 있을까?',
  'high',
  'draft',
  'ARC-IRAN-REGION',
  '기술적 관점: 정밀타격 기술의 한계가 정치적 결말을 막는다 (Freedman)',
  '구조적 관점: 불안정한 봉합이 전쟁의 귀결이다 (Rose)',
  '세 명의 전문가 모두 "이란 전쟁은 미국이 원하는 대로 깔끔하게 끝나지 않는다"는 데 동의한다. 군사적 우위가 정치적 승리로 이어지지 않는다는 공통 인식.',
  '공동 결론: 이란 전쟁은 깔끔하게 끝나지 않는다. 그러나 "왜" 안 끝나는지에 대한 이유가 다르다 — 기술의 한계인가, 국내정치의 함정인가, 전쟁 구조 자체의 문제인가.',
  '{
    "mode": "convergent",
    "shared_conclusion": "이란 전쟁은 깔끔하게 끝나지 않는다",
    "axes": [
      {
        "axis_id": "tech",
        "axis_label": "기술적 한계",
        "thesis": "첨단 정밀타격과 AI 작전으로 전쟁을 짧게 끝낼 수는 있어도 원하는 정치적 결과는 얻지 못한다. 체제 생존 의지가 강한 이란은 군사적 열세에서도 협상력을 유지한다.",
        "author": "로런스 프리드먼",
        "news_id": 555,
        "contrast_prompt": {
          "names_axis": "무기와 기술의 한계 — 아무리 정밀해도 정치적 결말은 못 얻는다",
          "distinguishes_from": {
            "politics": "이건 정치인 탓이 아니야. 프리드먼은 누가 집권하든 기술의 한계는 같다고 봐",
            "structure": "이건 \"전쟁은 원래 그래\"가 아니야. 프리드먼은 이란이 특히 강해서라고 봐"
          },
          "pivot_question": "네 근거는 \"무기의 한계\" 때문이야, 아니면 \"이란이라는 상대\"가 특별해서야?"
        }
      },
      {
        "axis_id": "politics",
        "axis_label": "국내정치 함정",
        "thesis": "전쟁에서 이겨도 국내정치 때문에 지속 가능한 질서를 못 만든다. 미국 내 여론과 정권 교체가 장기 전략을 불가능하게 한다.",
        "author": "이코노미스트",
        "news_id": 422,
        "contrast_prompt": {
          "names_axis": "미국 국내정치의 함정 — 이기고도 지속 가능한 질서를 못 만든다",
          "distinguishes_from": {
            "tech": "이건 무기 문제가 아니야. 이코노미스트는 기술이 아무리 좋아도 정권 교체 때문에 장기 전략이 안 된다고 봐",
            "structure": "이건 \"전쟁의 본질\"이 아니야. 이코노미스트는 미국 정치가 특히 문제라고 봐"
          },
          "pivot_question": "네 근거는 \"미국 정치가 특히 불안정해서\"야, 아니면 \"어느 나라든 정치는 다 그래서\"야?"
        }
      },
      {
        "axis_id": "structure",
        "axis_label": "전쟁의 구조",
        "thesis": "완전 해결은 불가능하고, 불안정한 봉합으로 끝나는 게 현대 전쟁의 구조적 귀결이다. 이건 이란만의 문제가 아니라 전쟁 자체의 성격이다.",
        "author": "기데온 로즈",
        "news_id": 528,
        "contrast_prompt": {
          "names_axis": "전쟁이라는 것 자체의 구조 — 상대가 누구든, 무기가 뭐든, 전쟁은 원래 깔끔히 안 끝난다",
          "distinguishes_from": {
            "tech": "이건 무기 탓이 아니야. 로즈는 무기 얘기를 아예 안 해. 기술이 발전해도 마찬가지라고 봐",
            "politics": "이건 정치인 탓이 아니야. 로즈는 누가 집권하든 전쟁 구조는 같다고 봐"
          },
          "pivot_question": "네 근거는 \"이란이 특별해서\"야, 아니면 \"전쟁이라는 게 원래 그래서\"야?"
        }
      }
    ],
    "fallback_adversarial": {
      "pro": "외교적 해결 가능론 (새 핵합의로 관리 가능)",
      "con": "군사적 해결 가능론 (압도적 힘으로 굴복시킬 수 있다)"
    },
    "counter_map": {
      "tech": "structure",
      "politics": "tech",
      "structure": "politics"
    }
  }'::jsonb,
  'A'
)
ON CONFLICT (quest_code) DO UPDATE SET
  hammer_hints = EXCLUDED.hammer_hints,
  updated_at = now();

-- 2. 생성된 퀘스트 ID 확인
SELECT id, quest_code, quest_title, hammer_hints->>'mode' as mode
FROM edu_daily_quests
WHERE quest_code = 'Q-IRAN-FOREVER-001';

-- 3. 기사 연결 (approve 전 필수 — 최소 3개 + primary 1개)
INSERT INTO edu_quest_articles (quest_id, news_id, role, sort_order, title, gist_url)
SELECT q.id, v.news_id, v.role, v.sort_order, v.title, 'https://www.thegist.co.kr/news/' || v.news_id
FROM edu_daily_quests q
CROSS JOIN (VALUES
  (555, 'primary', 1, '이란과 영원한 전쟁의 함정'),
  (422, 'context', 2, '끝나지 않는 전쟁의 높은 대가'),
  (528, 'context', 3, '이란은 베트남처럼, 우크라이나는 한국처럼')
) AS v(news_id, role, sort_order, title)
WHERE q.quest_code = 'Q-IRAN-FOREVER-001'
ON CONFLICT (quest_id, news_id) DO NOTHING;

-- 3-b. 스냅샷 backfill (§13-a — title만으로는 evidence 불가)
-- INSERT 후 반드시 실행:
--   php tools/edu_backfill_iran_article_snapshots.php --dry-run
--   php tools/edu_backfill_iran_article_snapshots.php
--   php tools/edu_verify_iran_article_snapshots.php
-- judgement_records + MySQL news에서 excerpt/why_important READ → edu_quest_articles PATCH (LLM 0회)

-- 4. 라이브 전환 (앱에 보이려면 approved + live_at 필수)
UPDATE edu_daily_quests
SET
  status = 'approved',
  pilot_priority = 'A',
  live_at = now(),
  expires_at = now() + interval '7 days',
  updated_at = now()
WHERE quest_code = 'Q-IRAN-FOREVER-001';

-- 5. (테스트용) 기존 중국 AI 퀘스트를 today 피드에서 내리기
-- today.php는 live_at 최신 1건만 표시 → 이란 퀘스트가 더 최신이면 자동 우선
-- 확실히 하려면 아래 실행:
-- UPDATE edu_daily_quests SET live_at = null WHERE quest_code = 'Q-AUTO-260612-29FD';

-- 6. 확인
SELECT quest_code, quest_title, status, live_at, hammer_hints->>'mode' AS mode
FROM edu_daily_quests
WHERE quest_code IN ('Q-IRAN-FOREVER-001', 'Q-AUTO-260612-29FD')
ORDER BY live_at DESC NULLS LAST;

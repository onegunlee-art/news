# GIST EDU — 배포 롤백 해시

사고 시 `git checkout <HEAD>` 또는 EC2에서 해당 커밋으로 되돌린 뒤 재배포.

| 날짜 | HEAD | 설명 |
|------|------|------|
| 2026-06-18 | `a0c9d6d` | evidence bridge 멘트 작업 직전 (catalog explore + quest_frame backfill fix) |
| 2026-06-18 | `a4601b0` | evidence bridge 멘트 배포 (고정 템플릿 + reason 구절, LLM refinePrompt 제거) |

## 참고

- Hammer 톤 완화: `95b533d` (PR #5, `e37c8f2` merge)
- Hammer 톤만 되돌릴 때: `e37c8f2` 직전 `6b3b9c0`

-- 기사 챗 answer_type: importance(왜 중요 칩), comparison(유사 사례 칩) 추가
-- 실행: 스테이징 → 프로덕션 (앱 배포 전 적용 권장)

ALTER TABLE `article_chat_messages`
  MODIFY COLUMN `answer_type` ENUM(
    'summary',
    'structure',
    'intent',
    'risk',
    'scenario',
    'importance',
    'comparison',
    'other'
  ) NULL DEFAULT NULL;

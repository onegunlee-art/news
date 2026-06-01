<?php
/**
 * Search cluster analysis — Layer 3 config (customer-facing, feature flags)
 */
return [
    'use_gist_tone' => true,
    'use_sectioned_output' => true,
    'external_intel_enabled' => false,
    'external_intel_max' => 3,
    'external_intel_timeout_sec' => 3,
    'external_similarity_min' => 0.55,
    'analysis_model' => 'gpt-4o-mini',
    'analysis_temperature' => 0.5,
    'analysis_max_tokens' => 2200,
    'system_prompt' => <<<'PROMPT'
당신은 "the gist" 수석 에디터입니다. 독자는 해외 뉴스를 한국어로 깊이 있게 이해하려는 지성인입니다.

【the gist 문체】
- 객관적·비판적·교육적 균형. 감정 과장·선동 금지.
- 한국어 존댓말(~이에요, ~거든요, ~있어요).
- 인사말 없이 바로 본론.

【문단 규칙 — 필수】
- 한 문단 = 하나의 논점만.
- 문단당 1~2문장. 3문장 이상 한 문단에 넣지 말 것.
- 섹션 사이에는 반드시 빈 줄 1개.
PROMPT,
];

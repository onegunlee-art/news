<?php
/**
 * Narrative Depth Contract — 검색 클러스터 분석 수준의 깊이·길이 기준 (3 Surface 공통)
 */
return [
    'analysis_structure' => [
        'core_conclusion',
        'perspective_compare',
        'implications',
    ],
    'min_chars' => [
        'synthesis_narrative' => 1200,
        'situation_narrative' => 800,
        'cluster_narrative' => 600,
        'collision_view' => 200,
        'executive_summary' => 400,
        'macro_so_what' => 200,
        'implication' => 120,
    ],
    'min_paragraphs' => [
        'synthesis_narrative' => 3,
        'situation_narrative' => 3,
        'cluster_narrative' => 3,
    ],
    'depth_pass_threshold' => 0.7,
    'model' => 'gpt-5.2',
    'temperature' => 0.5,
    'max_tokens' => [
        'search' => 2000,
        'strategic' => 8000,
        'weekly' => 12000,
        'synthesis' => 2500,
    ],
    'context_limits' => [
        'narration_per_article' => 800,
        'why_important' => 400,
        'max_articles' => 10,
    ],
    'system_prompt_synthesis' => '당신은 뉴스 분석 전문 AI입니다. 여러 기사를 종합하여 깊이 있는 분석을 제공합니다.',
];

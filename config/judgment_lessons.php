<?php
/**
 * Judgment Lesson Card — Moat layer configuration (Admin-only)
 */
return [
    'promotion_frequency' => 3,
    'extraction_model' => 'gpt-4o-mini',
    'extraction_temperature' => 0.2,
    'error_types' => [
        '확신_과잉',
        '출처_누락',
        '인과_비약',
        '관점_편향',
        '어투_이탈',
        '시점_혼동',
        'general',
    ],
    'cosmetic_path_patterns' => [
        '/^meta\./',
        '/\.language$/',
        '/executive_summary$/',
    ],
    'judgment_path_keywords' => [
        'scenario', 'collision', 'perspective', 'structural_shift',
        'implication', 'why_it_matters', 'probability', 'outcome',
        'view_a', 'view_b', 'confidence',
    ],
];

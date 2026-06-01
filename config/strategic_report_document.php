<?php
/**
 * Strategic Report Document — PDF/HTML 문서 생성 설정
 * UN Security Council Resolution 스타일 레이아웃 + the gist 브랜딩
 */
return [
    // 브랜딩
    'organization' => 'the gist.',
    'logo_path' => __DIR__ . '/../public/the-gist-logo.jpg',
    
    // 문서번호 체계: TG/SR/2026-W21
    'document_prefix' => 'TG/SR',
    
    // 배포 라벨 (UN의 "Distr.: General" 대응)
    'distribution_label' => 'Strategic Intelligence',
    'distribution_type' => 'Distr.: General',
    
    // PDF 설정
    'pdf' => [
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'margin_top' => 25,    // mm
        'margin_bottom' => 25,
        'margin_left' => 25,
        'margin_right' => 25,
        'dpi' => 150,
        'enable_remote' => false,  // 보안: 외부 리소스 비활성화
    ],
    
    // 폰트 설정
    'fonts' => [
        'body' => 'Noto Sans KR',      // 본문: 한국어 지원
        'heading' => 'Noto Sans KR',   // 제목
        'document_number' => 'serif',  // 문서번호: UN 문서 느낌
        'noto_path' => __DIR__ . '/../public/fonts/noto',
    ],
    
    // 섹션 라벨 (한국어)
    'section_labels' => [
        'synthesis_narrative' => '종합 분석',
        'executive_summary' => '요약',
        'structural_shift' => '구조적 변화',
        'situation' => '상황 분석',
        'timeline' => '주요 타임라인',
        'narrative_collisions' => '관점 충돌',
        'answer' => '시사점 및 전망',
        'scenarios' => '시나리오 분석',
        'action_matrix' => '핵심 행동 지표',
    ],
    
    // 이메일 템플릿
    'email' => [
        'subject_template' => '[the gist] 주간 지정학 전략 레포트 {report_week}',
        'sender_name' => 'the gist.',
    ],
];

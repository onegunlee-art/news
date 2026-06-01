<?php
declare(strict_types=1);

namespace App\Config;

/** Phase 1 SCQA JSON 스키마 설명 (프롬프트용) */
final class StrategicReportSchema
{
    public static function jsonSchemaDescription(): string
    {
        return <<<'JSON'
{
  "core_question": "이번 주 핵심 질문 (한국어, 1문장)",
  "synthesis_narrative": "검색 클러스터 분석과 동일한 3단 평문 (한국어 존댓말, 최소 3문단·1200자, \\n\\n로 문단 구분). 1) 핵심 결론 2) 관점 비교(일치 vs 충돌) 3) 향후 영향·종합 판단",
  "executive_summary": "경영진 요약 (한국어, 5~8문장, the gist 톤, 400자 이상)",
  "structural_shift": {
    "headline": "구조적 변화 한 줄 헤드라인",
    "from_pattern": "기존 질서/패턴",
    "to_pattern": "새로 부상하는 패턴",
    "why_now": "왜 지금 전환점인가",
    "evidence_source_ids": [0]
  },
  "situation": {
    "narrative": "상황 서술 (한국어, 4~6문단 분량의 하나의 문자열, 800자 이상, \\n\\n로 문단 구분)",
    "timeline": [{"date":"YYYY-MM-DD","event":"한국어","source_id":0}],
    "anchor_entities": ["국가·기관·인물 (한국어 표기 우선)"]
  },
  "complication": {
    "trigger": "복잡성을 촉발한 계기 (한국어)",
    "narrative_collisions": [
      {
        "label": "충돌명 (예: 미국-이ran 에너지 프레임)",
        "actor_a": "관점 A 주체",
        "view_a": "관점 A (한국어, 2~3문장, 200자 이상)",
        "actor_b": "관점 B 주체",
        "view_b": "관점 B (한국어, 2~3문장, 200자 이상)",
        "collision": "두 관점이 충돌하는 지점 (한국어, 2~3문장)",
        "source_ids": [0]
      }
    ],
    "perspectives": [{"viewpoint":"한국어","source_id":0,"quote":"인용 또는 요지 (한국어)"}]
  },
  "question": "SCQA Question (한국어)",
  "answer": {
    "implication": "시사점 (한국어, 2~4문장, 120자 이상)",
    "why_it_matters_chain": ["인과 1단계","2단계","3단계 이상 (한국어)"],
    "scenarios": [{"type":"base|upside|downside","probability":60,"outcome":"한국어","prediction_signal":"관측 신호 (한국어)"}],
    "action_matrix": {
      "watch": ["주시할 변수 (한국어)"],
      "consider": ["검토할 옵션 (한국어)"],
      "act": ["선제 대응 (한국어, 정책·기업 관점)"]
    }
  },
  "meta": {"source_count":{},"confidence":"high|medium|low","language":"ko"}
}
JSON;
    }
}

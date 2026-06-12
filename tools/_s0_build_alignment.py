#!/usr/bin/env python3
"""Build arc alignment/conflict doc from search READ + manual_arc mapping."""
import json
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Per-arc alignment/conflict (verified via search.php clusters + article description READ)
ARC_ALIGNMENT = [
    ("ARC-AI-JOBS", "AI 규제·일자리·사회계약",
     "AI가 일자리를 대량 대체할 수 있다는 공포와 정부·기업의 선제 대응 필요성에 대한 인식이 공통된다. 규제·교육·도구 활용 논의 모두 '사회적 충격을 막아야 한다'는 전제를 공유한다.",
     "안전망·규제 강화 vs 성장·도구 활용 자유, 청소년은 전면 금지 vs 사용 방식 교육, 기업은 AI를 KPI·자동화에 넣을지 인지·창의 보존에 둘지 갈린다.",
     "search.php cluster: AI 규제와 고용 문제 · articles 507,72,297"),
    ("ARC-AI-GEOPOL", "미중 AI 경쟁·인재·전력",
     "AI 패권은 칩·인재·전력·데이터가 복합 전장이며 미중 양국이 국가안보 프레임으로 경쟁한다는 점에 기사들이 수렴한다.",
     "미국은 수출규제·동맹으로 막을지 vs 중국은 저렴한 전력·인재 규모로 역전할지, 군사 AI 활용은 억제할지 가속할지 입장이 갈린다.",
     "search.php · articles 267,248,366"),
    ("ARC-AI-SECURITY", "AI 사이버·군사·규제 삼중딜레마",
     "자율형 AI 에이전트·사이버공격·군사 활용이 안보 리스크를 키운다는 경고와 규제 필요성이 공통된다.",
     "규제 삼중딜레마(안보·경제·사회) 때문에 강한 규제는 성장을 해친다 vs 무분별한 배포는 재난에 가깝다; 중국은 통제·대중화 균형, 미국은 기업·정부 충돌.",
     "manual READ 371,72,270"),
    ("ARC-IRAN-NUKE", "이란 핵·제재·외교",
     "이란 핵 프로그램과 제재·외교 타결 가능성이 중동 안보의 핵심 변수라는 인식이 일치한다.",
     "강경 제재·군사 옵션 vs 협상·JCPOA 복원, 이스라엘 선제타격 정당성 vs 외교적 시간 확보가 충돌한다.",
     "search.php · articles 196,152,238"),
    ("ARC-IRAN-REGION", "이란·이스라엘·호르무즈·유가",
     "지역 충돌이 호르무즈·유가·글로벌 공급망에 파급된다는 인과가 공통된다.",
     "이스라엘·미국의 강경 대응 vs 외교적 봉합, 유가 상승을 막기 위한 전략비축 방출 vs 공급 차질 감수, 한국 등 수입국은 안보·경제 중 어느 쪽을 우선할지 갈린다.",
     "search.php · articles 528,384,132"),
    ("ARC-US-CN-TRADE", "미중 관세·탈활성화·공급망",
     "미중 경쟁이 관세·공급망·기술 표준으로 확장되며 양측 모두 자국 산업 보호를 추진한다는 점이 일치한다.",
     "탈중국·공급망 재편 비용을 감수할지 vs 효율·저가 수입 유지, 동맹국과의 관세 조율 vs 미국 우선 관세가 충돌한다.",
     "search.php · articles 283,392,119"),
    ("ARC-TRUMP-TARIFF", "트럼프 관세·글로벌 무역",
     "관세를 협상 레버리지·산업 보호 수단으로 본다는 프레임이 공통된다.",
     "단기 고용·제조 회복 vs 소비자 물가·무역보복, 다자 무역질서 유지 vs 양자 거래 우선이 갈린다.",
     "search.php · articles 397,503,375"),
    ("ARC-CLIMATE-ENERGY", "기후·에너지 전환·재생",
     "에너지 전환과 기후 리스크가 경제·산업 정책의 중심 축이라는 점에 일치한다.",
     "재생에너지로 가스·석유를 얼마나 빨리 대체할지, 전환 투자 vs 당장 일자리·물가, 중동 산유국은 수출 의존 vs 다각화 속도가 충돌한다.",
     "search.php · articles 240,291,93"),
    ("ARC-OIL-GAS", "유가·LNG·에너지 안보",
     "에너지 공급망·지정학이 가격과 안보를 동시에 좌우한다는 분석이 공통된다.",
     "LNG·전략비축 확대 vs 시장·재생 의존, 유가 상승 시 소비 억제 vs 생산국 이익, AI 데이터센터 전력 수요가 구조를 바꾼다는 관점이 겹친다.",
     "search.php · articles 287,496,150"),
    ("ARC-EV-CHINA", "전기차·중국·보조금·경쟁",
     "중국 EV·배터리 우위가 글로벌 완성차 업체의 딜레마를 만든다는 점이 일치한다.",
     "보조금·관세로 국내 산업 보호 vs 개방 경쟁, 중국 시장 참여 vs 공급망 다변화, 유가·전력 비용이 전환 속도를 좌우한다는 인식이 공유·충돌한다.",
     "search.php · articles 459,506,392"),
    ("ARC-CHIP-SUPPLY", "반도체·공급망·수출규제",
     "반도체가 AI·국방·경제의 공통 병목이며 동북아 집중이 리스크라는 점이 일치한다.",
     "수출규제·자국 생산 vs 글로벌 분업 효율, 초과이익·보조금 vs 시장 경쟁, 일본·대만·한국 역할 분담이 갈린다.",
     "search.php · articles 220,558,513"),
    ("ARC-JAPAN-DEFENSE", "일본 방위·안보·미일",
     "일본의 방위 예산·역할 확대와 미일동맹 재정의가 동아시아 균형의 변수라는 점이 공통된다.",
     "전통적 방위 vs 적극적 견제, 대만·반도체와 연계한 경제안보 vs 순수 군사 동맹, 역사·여론 제약 vs 안보 현실이 충돌한다.",
     "search.php · articles 452,546,433"),
    ("ARC-TAIWAN-STRAIT", "대만·중국·회담·위기",
     "대만 해협이 미중 기술·군사·경제의 교차점이라는 인식이 일치한다.",
     "군사 억제·동맹 공약 vs 외교적 모호성 유지, 회담·대화 vs 압박·제재, 미국의 아시아 개입 범위가 핵심 충돌축이다.",
     "search.php · articles 514,427,521"),
    ("ARC-UKRAINE-WAR", "우크라이나·전쟁·NATO",
     "장기전이 유럽 안보·NATO 부담·글로벌 에너지에 파급된다는 분석이 공통된다.",
     "군사·재정 지원 지속 vs 협상·동결, NATO 확대·강경 vs 완화, 한반도·중동과 연계한 에너지·안보 비용 분담이 갈린다.",
     "search.php · articles 87,437,263"),
    ("ARC-INFLATION-FED", "인플레·금리·경기",
     "물가·금리·성장 트레이드오프가 정책의 중심이라는 점이 일치한다.",
     "인플레 억제를 위한 고금리 유지 vs 경기·고용 보호를 위한 금리 인하, AI·에너지 수요가 구조적 인플레 원인이라는 관점 vs 일시적 충격 해석이 갈린다.",
     "search.php · articles 150,210,338"),
]

def main():
    out = os.path.join(ROOT, "docs", "GIST_EDU_ARC_ALIGNMENT.md")
    with open(out, "w", encoding="utf-8") as f:
        f.write("# GIST EDU Sprint 0 — arc별 일치·불일치 검증\n\n")
        f.write("> **방법:** production `search.php` READ (벡터 검색·clusters·insight) + 기사 `description` 수동 검토  \n")
        f.write("> **Partner RAG:** `include_analysis:true` 경로는 Sprint 0 동일 검증 가능 (로컬 키 없음 → search READ로 대체)  \n")
        f.write("> **코어 영향:** 없음 (SELECT·공개 API 호출만)\n\n")
        f.write("## 검증 기록\n\n")
        f.write("| manual_arc | alignment_summary | conflict_summary | 검증 출처 |\n")
        f.write("|------------|-------------------|------------------|----------|\n")
        for arc_id, label, align, conflict, source in ARC_ALIGNMENT:
            a = align.replace("|", "/")
            c = conflict.replace("|", "/")
            f.write(f"| **{arc_id}** | {a} | {c} | {source} |\n")
        f.write("\n## B-auto 시드 필드 (arc당)\n\n```json\n")
        f.write(json.dumps({
            "manual_arc": "ARC-AI-JOBS",
            "alignment_summary": "...",
            "conflict_summary": "...",
            "verified_at": "2026-06-08",
            "method": "search_read+manual"
        }, ensure_ascii=False, indent=2))
        f.write("\n```\n")
    print(f"Wrote {out}")

if __name__ == "__main__":
    main()

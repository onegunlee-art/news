#!/usr/bin/env python3
"""
GIST EDU 퀘스트 생성기 (Sprint 0 플랜 로직)

규칙:
- GIST published 기사만 (search.php READ 메타)
- 동일 manual_arc 3건+
- alignment_summary / conflict_summary 필수
- role: primary | context | counter
- human approve 템플릿 → status: approved
- 코어 DB/API 변경 없음
"""
from __future__ import annotations

import json
import glob
import os
import sys
from datetime import datetime, timezone

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# arc별 일치·불일치 (GIST_EDU_ARC_ALIGNMENT.md 동기)
ARC_META = {
    "ARC-AI-JOBS": (
        "AI 일자리 충격 가능성과 선제적 사회 대응 필요성에 대한 인식 공유",
        "안전망·규제 강화 vs 성장·도구 활용 자유, 인지 보존 vs 자동화 가속",
    ),
    "ARC-AI-GEOPOL": (
        "AI 패권은 칩·인재·전력 복합 전장이며 미중 양국이 국가안보 프레임으로 경쟁",
        "미국 수출규제·동맹 vs 중국 전력·인재 규모, 군사 AI 억제 vs 가속",
    ),
    "ARC-AI-SECURITY": (
        "자율 AI·사이버·군사 리스크와 규제 필요성 공통 인식",
        "강규제 vs 성장, 미·중 정부·기업 입장 충돌",
    ),
    "ARC-IRAN-NUKE": (
        "이란 핵·제재가 중동 안보 핵심 변수",
        "강경 제재·군사 옵션 vs 협상·JCPOA 복원",
    ),
    "ARC-IRAN-REGION": (
        "지역 충돌이 호르무즈·유가·공급망에 파급",
        "강경 대응 vs 외교 봉합, 유가 안보 vs 안보 현실",
    ),
    "ARC-US-CN-TRADE": (
        "미중 경쟁이 관세·공급망·기술 표준으로 확장",
        "탈중국 비용 vs 효율, 동맹 조율 vs 자국 이익",
    ),
    "ARC-TRUMP-TARIFF": (
        "관세를 협상·산업 보호 레버리지로 보는 프레임 공유",
        "단기 고용·제조 vs 소비자 물가·보복",
    ),
    "ARC-CLIMATE-ENERGY": (
        "에너지 전환·기후 리스크가 경제·산업 정책 중심",
        "급진 탈탄소 vs 점진 전환, 재생 vs 가스 브릿지",
    ),
    "ARC-OIL-GAS": (
        "에너지 공급망·지정학이 가격·안보 동시 좌우",
        "LNG·비축 확대 vs 재생 의존, AI 전력 수요 구조 변화",
    ),
    "ARC-EV-CHINA": (
        "중국 EV 우위가 글로벌 완성차 딜레마",
        "보조금·관세 보호 vs 시장 개방",
    ),
    "ARC-CHIP-SUPPLY": (
        "반도체가 AI·안보 병목, 동북아 집중 리스크",
        "수출규제·자국 생산 vs 글로벌 분업",
    ),
    "ARC-JAPAN-DEFENSE": (
        "일본 방위 확대가 미일·동아시아 균형 변수",
        "안보 현실 vs 역사·여론, 경제안보 vs 군사 확장",
    ),
    "ARC-TAIWAN-STRAIT": (
        "대만 해협이 미중 기술·군사·경제 교차점",
        "군사 억제·동맹 vs 외교적 모호성",
    ),
    "ARC-UKRAINE-WAR": (
        "장기전이 유럽 안보·에너지에 파급",
        "지원 지속 vs 협상·동결",
    ),
    "ARC-INFLATION-FED": (
        "물가·금리·성장 트레이드오프가 정책 중심",
        "고금리 유지 vs 경기 방어",
    ),
}

# human-approved 퀘스트 템플릿 (플랜 S0-3)
QUEST_TEMPLATES = [
    {
        "quest_code": "Q-G01", "grade_band": "middle", "manual_arc": "ARC-AI-JOBS",
        "quest_title": "정부는 AI 일자리 대란 전에 안전망을 깔아야 할까?",
        "pro_line": "AI가 일자리를 바꿀 수 있으니 정부가 미리 안전망·재교육을 준비해야 한다",
        "con_line": "아직 대규모 실업이 없으니 성장을 막는 규제·지출은 이르다",
        "articles": [(507, "primary"), (72, "context"), (462, "context"), (297, "counter")],
        "hammer_hints": {"pro": "지금 고용지표만 보면 안 된다", "con": "과잉 규제는 스타트업을 죽인다"},
        "scores": {"no_answer": 5, "life": 5, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G02", "grade_band": "middle", "manual_arc": "ARC-AI-JOBS",
        "quest_title": "청소년 AI 사용은 시간 제한보다 '어떻게 쓰느냐'를 가르쳐야 할까?",
        "pro_line": "사용 방식·비판적 사고 교육이 전면 금지보다 효과적이다",
        "con_line": "미성년자는 전면 금지·엄격한 시간 제한이 필요하다",
        "articles": [(288, "primary"), (462, "context"), (507, "context"), (72, "counter")],
        "hammer_hints": {"pro": "금지해도 우회한다", "con": "비판적 사용을 믿을 수 있나"},
        "scores": {"no_answer": 4, "life": 5, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G03", "grade_band": "middle", "manual_arc": "ARC-AI-GEOPOL",
        "quest_title": "미국이 막아도 중국이 AI 인재 경쟁에서 이길 수 있을까?",
        "pro_line": "인재·전력·규모 우위로 중국이 역전할 수 있다",
        "con_line": "칩 규제·동맹·혁신 생태계로 미국이 우위를 유지한다",
        "articles": [(267, "primary"), (248, "context"), (366, "counter"), (126, "context")],
        "hammer_hints": {"pro": "반도체 없어도 전력·인재가 있다", "con": "최첨단 칩 없이 천장이 있다"},
        "scores": {"no_answer": 4, "life": 4, "debate": 4}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G04", "grade_band": "middle", "manual_arc": "ARC-EV-CHINA",
        "quest_title": "한국도 전기차 보조금으로 외국 브랜드를 막아야 할까?",
        "pro_line": "국내 산업·일자리 보호를 위해 보조금·관세가 필요하다",
        "con_line": "소비자 선택·기술 경쟁을 위해 시장 개방이 맞다",
        "articles": [(459, "primary"), (506, "context"), (225, "context"), (392, "counter")],
        "hammer_hints": {"pro": "일본·미국도 보호한다", "con": "보호하면 가격만 오른다"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G05", "grade_band": "middle", "manual_arc": "ARC-CLIMATE-ENERGY",
        "quest_title": "기후 때문에 지금 당장 석유·가스를 줄여야 할까?",
        "pro_line": "되돌릴 수 없는 기후 리스크 때문에 지금 전환 투자가 필요하다",
        "con_line": "당장 일자리·물가가 더 급해 점진적 전환이 맞다",
        "articles": [(240, "primary"), (291, "context"), (93, "context"), (195, "counter")],
        "hammer_hints": {"pro": "중동 사태가 보여줬다", "con": "당장 전기세·일자리"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G06", "grade_band": "middle", "manual_arc": "ARC-CHIP-SUPPLY",
        "quest_title": "반도체를 꼭 '우리나라만' 만들 수 있게 해야 할까?",
        "pro_line": "AI·안보 병목이라 공급망 자립·동맹 생산이 필수다",
        "con_line": "글로벌 분업이 더 싸고 빠르다",
        "articles": [(558, "primary"), (513, "context"), (532, "context"), (220, "counter")],
        "hammer_hints": {"pro": "대만 리스크", "con": "혼자 다 만들 수 없다"},
        "scores": {"no_answer": 4, "life": 4, "debate": 4}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G07", "grade_band": "middle", "manual_arc": "ARC-IRAN-NUKE",
        "quest_title": "이란 핵 문제는 협상으로 풀어야 할까, 압박으로 풀어야 할까?",
        "pro_line": "제재·외교 협상이 군사 충돌보다 낫다",
        "con_line": "강한 압박·제재 없이는 이란이 핵을 포기하지 않는다",
        "articles": [(196, "primary"), (238, "context"), (233, "context"), (263, "counter")],
        "hammer_hints": {"pro": "전쟁 비용", "con": "협상은 시간만 번다"},
        "scores": {"no_answer": 4, "life": 3, "debate": 5}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G08", "grade_band": "middle", "manual_arc": "ARC-TRUMP-TARIFF",
        "quest_title": "미국식 관세가 우리나라 산업을 지키는 방법일까?",
        "pro_line": "관세로 불공정 무역·수입 의존을 줄일 수 있다",
        "con_line": "관세는 소비자 물가·보복만 키운다",
        "articles": [(397, "primary"), (503, "context"), (375, "context"), (237, "counter")],
        "hammer_hints": {"pro": "중국 수출도 버텼다", "con": "관세는 세금이다"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G09", "grade_band": "middle", "manual_arc": "ARC-JAPAN-DEFENSE",
        "quest_title": "일본이 군사력을 키우는 걸 우리는 막아야 할까, 받아들여야 할까?",
        "pro_line": "동아시아 균형·미일동맹 강화에 필요하다",
        "con_line": "역사·지역 긴장을 키우므로 제한해야 한다",
        "articles": [(452, "primary"), (546, "context"), (433, "context"), (558, "counter")],
        "hammer_hints": {"pro": "북한·중국", "con": "과거를 잊지 말자"},
        "scores": {"no_answer": 4, "life": 3, "debate": 5}, "pilot_priority": "C",
    },
    {
        "quest_code": "Q-G10", "grade_band": "middle", "manual_arc": "ARC-UKRAINE-WAR",
        "quest_title": "우크라이나에 계속 지원해야 할까?",
        "pro_line": "침략에 맞서 지원하지 않으면 국제 질서가 무너진다",
        "con_line": "장기전 비용이 너무 크니 협상·동결이 낫다",
        "articles": [(87, "primary"), (437, "context"), (263, "context"), (152, "counter")],
        "hammer_hints": {"pro": "오늘 우크라, 내일?", "con": "우리 세금·유가"},
        "scores": {"no_answer": 5, "life": 3, "debate": 5}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G11", "grade_band": "high", "manual_arc": "ARC-AI-SECURITY",
        "quest_title": "AI 규제는 '안보·경제·사회' 셋 다 잡을 수 있을까?",
        "pro_line": "삼중 목표를 동시에 추구하는 규제 설계가 가능하다",
        "con_line": "AI 삼중딜레마 때문에 한쪽을 포기하지 않으면 실패한다",
        "articles": [(72, "primary"), (270, "context"), (402, "context"), (371, "counter")],
        "hammer_hints": {"pro": "EU식 단계 규제", "con": "규제는 중국에만 이득"},
        "scores": {"no_answer": 5, "life": 4, "debate": 4}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G12", "grade_band": "high", "manual_arc": "ARC-AI-SECURITY",
        "quest_title": "자율형 AI 해킹 도구는 국가가 전면 금지해야 할까?",
        "pro_line": "인간 개입 없는 공격 에이전트는 금지·통제해야 한다",
        "con_line": "방어·연구 목적 허용 없이는 뒤처진다",
        "articles": [(371, "primary"), (126, "context"), (270, "context"), (72, "counter")],
        "hammer_hints": {"pro": "앤스로픽 사례", "con": "방패 없이 칼만 금지?"},
        "scores": {"no_answer": 4, "life": 4, "debate": 4}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G13", "grade_band": "high", "manual_arc": "ARC-US-CN-TRADE",
        "quest_title": "미중 '탈동조화'를 한국도 따라가야 할까?",
        "pro_line": "공급망·기술 안보를 위해 중국 의존을 줄여야 한다",
        "con_line": "시장·효율을 위해 중국과 경제 협력을 유지해야 한다",
        "articles": [(283, "primary"), (392, "context"), (119, "context"), (252, "counter")],
        "hammer_hints": {"pro": "반도체·희토류", "con": "중국이 최대 시장"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G14", "grade_band": "high", "manual_arc": "ARC-TAIWAN-STRAIT",
        "quest_title": "대만 위기 때 미국이 군사 개입해야 할까?",
        "pro_line": "동맹 신뢰·반도체 안보를 위해 개입 약속이 필요하다",
        "con_line": "대규모 전쟁 위험 때문에 전략적 모호성이 낫다",
        "articles": [(514, "primary"), (427, "context"), (521, "context"), (119, "counter")],
        "hammer_hints": {"pro": "삼성·TSMC", "con": "핵대국과 전쟁?"},
        "scores": {"no_answer": 5, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G15", "grade_band": "high", "manual_arc": "ARC-IRAN-REGION",
        "quest_title": "이란·이스라엘 충돌 때 유가 상승을 감수하고 제재를 강화해야 할까?",
        "pro_line": "안보·규범을 위해 강경 대응이 필요하다",
        "con_line": "유가·경제 충격이 커서 외교적 완화가 우선이다",
        "articles": [(528, "primary"), (384, "context"), (132, "context"), (437, "counter")],
        "hammer_hints": {"pro": "호르무즈 봉쇄", "con": "기름값·물가"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G16", "grade_band": "high", "manual_arc": "ARC-CLIMATE-ENERGY",
        "quest_title": "재생에너지만으로 천연가스를 완전히 없앨 수 있을까?",
        "pro_line": "기술·투자가 충분하면 가스 없이도 전환 가능하다",
        "con_line": "당분간 가스가 전환의 브릿지 연료로 필요하다",
        "articles": [(240, "primary"), (291, "context"), (287, "context"), (193, "counter")],
        "hammer_hints": {"pro": "태양광·저장", "con": "무풍·무일 때"},
        "scores": {"no_answer": 4, "life": 3, "debate": 5}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G17", "grade_band": "high", "manual_arc": "ARC-INFLATION-FED",
        "quest_title": "물가 잡으려면 지금 금리를 더 오래 높게 유지해야 할까?",
        "pro_line": "인플레 기대를 잡으려면 고금리 유지가 필요하다",
        "con_line": "경기·고용이 더 위험하니 금리 인하·완화가 맞다",
        "articles": [(150, "primary"), (210, "context"), (338, "context"), (432, "counter")],
        "hammer_hints": {"pro": "1970년 교훈", "con": "실업이 더 무섭다"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "B",
    },
    {
        "quest_code": "Q-G18", "grade_band": "high", "manual_arc": "ARC-TAIWAN-STRAIT",
        "quest_title": "미국이 아시아를 중국에 양보하는 '세력권'을 받아들여야 할까?",
        "pro_line": "현실적으로 영향력 분할을 인정하는 편이 전쟁을 막는다",
        "con_line": "양보는 곧 동맹 붕괴·기술 종속으로 이어진다",
        "articles": [(521, "primary"), (119, "context"), (514, "context"), (427, "counter")],
        "hammer_hints": {"pro": "전쟁 회피", "con": "대만·반도체"},
        "scores": {"no_answer": 5, "life": 3, "debate": 4}, "pilot_priority": "C",
    },
    {
        "quest_code": "Q-G19", "grade_band": "high", "manual_arc": "ARC-CHIP-SUPPLY",
        "quest_title": "중국에 반도체 수출을 막는 건 정당한가?",
        "pro_line": "안보·인권·불공정 경쟁 때문에 제한이 필요하다",
        "con_line": "기술 확산·무역 규범을 해치고 역효과가 난다",
        "articles": [(558, "primary"), (513, "context"), (220, "context"), (532, "counter")],
        "hammer_hints": {"pro": "군사 AI", "con": "중국이 더 빨리 자립"},
        "scores": {"no_answer": 4, "life": 4, "debate": 5}, "pilot_priority": "A",
    },
    {
        "quest_code": "Q-G20", "grade_band": "high", "manual_arc": "ARC-AI-JOBS",
        "quest_title": "직장에서 AI에게 '생각'을 맡기는 것까지 허용해야 할까?",
        "pro_line": "생산성·의사결정 보조로 적극 활용해야 한다",
        "con_line": "인지적 항복으로 조직·개인 판단력이 약해진다",
        "articles": [(462, "primary"), (297, "context"), (507, "context"), (288, "counter")],
        "hammer_hints": {"pro": "계산기·GPS와 같다", "con": "틀려도 믿는다"},
        "scores": {"no_answer": 5, "life": 5, "debate": 5}, "pilot_priority": "A",
    },
]


def load_articles() -> dict[int, dict]:
    by_id: dict[int, dict] = {}
    for path in glob.glob(os.path.join(ROOT, "tools", "_s0_search_result_*.json")):
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
        for a in data.get("results") or []:
            nid = int(a["news_id"])
            if nid not in by_id:
                by_id[nid] = {
                    "news_id": nid,
                    "title": a.get("title", ""),
                    "topic_label": a.get("topic_label", ""),
                    "category": a.get("category", ""),
                    "published_at": (a.get("published_at") or "")[:10],
                    "gist_url": f"https://www.thegist.co.kr/news/{nid}",
                }
    return by_id


def build_quest(tpl: dict, articles: dict[int, dict]) -> dict:
    arc = tpl["manual_arc"]
    align, conflict = ARC_META[arc]
    article_refs = []
    for nid, role in tpl["articles"]:
        meta = articles.get(nid)
        if not meta:
            raise ValueError(f"{tpl['quest_code']}: missing article {nid} in search pool")
        article_refs.append({**meta, "role": role})

    scores = tpl["scores"]
    total = scores["no_answer"] + scores["life"] + scores["debate"]

    return {
        "quest_code": tpl["quest_code"],
        "quest_title": tpl["quest_title"],
        "grade_band": tpl["grade_band"],
        "status": "approved",
        "manual_arc": arc,
        "pro_line": tpl["pro_line"],
        "con_line": tpl["con_line"],
        "alignment_summary": align,
        "conflict_summary": conflict,
        "articles": article_refs,
        "hammer_hints": tpl["hammer_hints"],
        "scores": {**scores, "total": total},
        "pilot_priority": tpl["pilot_priority"],
        "fsm_stages": ["commit", "hammer", "reflection", "writing", "growth"],
        "generated_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "source": "generate_edu_quests.py",
    }


def validate(quests: list[dict]) -> list[str]:
    errors = []
    for q in quests:
        code = q["quest_code"]
        if len(q["articles"]) < 3:
            errors.append(f"{code}: articles < 3")
        if not q.get("conflict_summary", "").strip():
            errors.append(f"{code}: empty conflict_summary")
        roles = {a["role"] for a in q["articles"]}
        if "primary" not in roles:
            errors.append(f"{code}: missing primary role")
        if q["scores"]["total"] < 12:
            errors.append(f"{code}: score total {q['scores']['total']} < 12")
    return errors


def main() -> int:
    articles = load_articles()
    quests = [build_quest(t, articles) for t in QUEST_TEMPLATES]
    errors = validate(quests)
    if errors:
        for e in errors:
            print(f"GATE FAIL: {e}", file=sys.stderr)
        return 1

    out_dir = os.path.join(ROOT, "docs")
    os.makedirs(out_dir, exist_ok=True)
    payload = {
        "meta": {
            "version": "v2-gist-native",
            "count": len(quests),
            "middle": sum(1 for q in quests if q["grade_band"] == "middle"),
            "high": sum(1 for q in quests if q["grade_band"] == "high"),
            "generator": "tools/generate_edu_quests.py",
            "principles": [
                "gist_published_only",
                "min_3_articles",
                "alignment_conflict_required",
                "human_approve",
                "core_read_only",
            ],
            "gate_passed": True,
        },
        "quests": quests,
    }
    json_path = os.path.join(out_dir, "GIST_EDU_QUESTS.json")
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    # 일일 퀘스트 미리보기 (첫 3건)
    preview_path = os.path.join(out_dir, "GIST_EDU_QUEST_PREVIEW.md")
    with open(preview_path, "w", encoding="utf-8") as f:
        f.write("# GIST EDU — 생성된 퀘스트 미리보기\n\n")
        f.write(f"> 자동 생성: `{payload['meta']['generator']}` · {payload['meta']['count']}건 approved\n\n")
        for q in quests[:3]:
            f.write(f"## {q['quest_code']} · {q['quest_title']}\n\n")
            f.write(f"- **학년:** {q['grade_band']} · **arc:** {q['manual_arc']}\n")
            f.write(f"- **찬성:** {q['pro_line']}\n")
            f.write(f"- **반대:** {q['con_line']}\n")
            f.write(f"- **일치:** {q['alignment_summary']}\n")
            f.write(f"- **불일치:** {q['conflict_summary']}\n")
            f.write("- **기사:**\n")
            for a in q["articles"]:
                f.write(f"  - [{a['news_id']}] {a['title'][:40]}… (`{a['role']}`)\n")
            f.write("\n")

    print(f"OK: {len(quests)} quests -> {json_path}")
    print(f"Preview: {preview_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

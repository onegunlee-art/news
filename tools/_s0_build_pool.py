#!/usr/bin/env python3
"""Sprint 0: aggregate search results into article pool + manual arcs."""
import json
import glob
import os
from collections import defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# manual_arc definitions: label -> {query_topic, news_ids (filled), min 3}
MANUAL_ARCS = [
    ("ARC-AI-JOBS", "AI 규제·일자리·사회계약", "ai", [507, 72, 462, 297, 288]),
    ("ARC-AI-GEOPOL", "미중 AI 경쟁·인재·전력", "ai", [267, 248, 366, 126, 270]),
    ("ARC-AI-SECURITY", "AI 사이버·군사·규제 삼중딜레마", "ai", [371, 126, 72, 270, 402]),
    ("ARC-IRAN-NUKE", "이란 핵·제재·외교", "iran", [196, 152, 238, 233, 263]),
    ("ARC-IRAN-REGION", "이란·이스라엘·호르무즈·유가", "iran", [528, 384, 132, 437, 290]),
    ("ARC-US-CN-TRADE", "미중 관세·탈활성화·공급망", "trade", [283, 392, 397, 119, 252]),
    ("ARC-TRUMP-TARIFF", "트럼프 관세·글로벌 무역", "trade", [397, 503, 375, 497, 237]),
    ("ARC-CLIMATE-ENERGY", "기후·에너지 전환·재생", "climate", [240, 291, 93, 193, 195]),
    ("ARC-OIL-GAS", "유가·LNG·에너지 안보", "climate", [287, 496, 150, 193, 384]),
    ("ARC-EV-CHINA", "전기차·중국·보조금·경쟁", "ev", [459, 506, 299, 225, 392]),
    ("ARC-CHIP-SUPPLY", "반도체·공급망·수출규제", "chip", [220, 558, 513, 532, 240]),
    ("ARC-JAPAN-DEFENSE", "일본 방위·안보·미일", "chip", [452, 546, 432, 433, 558]),
    ("ARC-TAIWAN-STRAIT", "대만·중국·회담·위기", "trade", [514, 427, 521, 119, 452]),
    ("ARC-UKRAINE-WAR", "우크라이나·전쟁·NATO", "iran", [87, 437, 384, 263, 152]),
    ("ARC-INFLATION-FED", "인플레·금리·경기", "ev", [150, 210, 338, 432, 375]),
]

def load_all_articles():
    by_id = {}
    for path in glob.glob(os.path.join(ROOT, "tools", "_s0_search_result_*.json")):
        with open(path, encoding="utf-8") as f:
            d = json.load(f)
        for a in d.get("results") or d.get("articles") or []:
            nid = a.get("news_id") or a.get("id")
            if nid and nid not in by_id:
                by_id[nid] = {
                    "news_id": nid,
                    "title": a.get("title", ""),
                    "topic_label": a.get("topic_label", ""),
                    "category": a.get("category", ""),
                    "category_parent": a.get("category_parent") or a.get("category", ""),
                    "published_at": (a.get("published_at") or "")[:10],
                    "entities": a.get("entities") or [],
                    "region": a.get("region") or [],
                    "safety_ok": "Y",
                }
    return by_id

def main():
    by_id = load_all_articles()
    rows = []
    arc_rows = []
    for arc_id, arc_label, src, ids in MANUAL_ARCS:
        found = [by_id[i] for i in ids if i in by_id]
        if len(found) < 3:
            print(f"WARN {arc_id}: only {len(found)} articles in pool")
        for i, art in enumerate(found):
            role = "primary" if i == 0 else ("counter" if i == len(found) - 1 and len(found) >= 3 else "context")
            rows.append({**art, "manual_arc": arc_id, "manual_arc_label": arc_label, "role": role})
        arc_rows.append({
            "manual_arc": arc_id,
            "label": arc_label,
            "article_count": len(found),
            "news_ids": [a["news_id"] for a in found],
            "safety_ok": "Y",
        })

    # dedupe full pool
    seen = set()
    pool = []
    for r in rows:
        k = r["news_id"]
        if k not in seen:
            seen.add(k)
            pool.append(r)

    out_md = os.path.join(ROOT, "docs", "GIST_EDU_ARTICLE_POOL.md")
    with open(out_md, "w", encoding="utf-8") as f:
        f.write("# GIST EDU Sprint 0 — 기사 후보 풀 (READ ONLY)\n\n")
        f.write("> **상태:** Sprint 0 산출 · 코어 DB 미변경 · `manual_arc` 수동 그룹\n")
        f.write(f"> **기사 수:** {len(pool)}편 (arc 중복 포함) · **arc:** {len(arc_rows)}개\n\n")
        f.write("## manual_arc 요약 (3건+ 후보)\n\n")
        f.write("| manual_arc | 라벨 | 기사 수 | news_ids | safety |\n")
        f.write("|------------|------|---------|----------|--------|\n")
        for a in arc_rows:
            ids = ", ".join(str(x) for x in a["news_ids"])
            f.write(f"| {a['manual_arc']} | {a['label']} | {a['article_count']} | {ids} | {a['safety_ok']} |\n")
        f.write("\n## 전체 기사 목록\n\n")
        f.write("| news_id | published | category | topic_label | manual_arc | role | title |\n")
        f.write("|---------|-----------|----------|-------------|------------|------|-------|\n")
        for r in sorted(rows, key=lambda x: (x["manual_arc"], x["news_id"])):
            title = r["title"].replace("|", "/")[:50]
            f.write(f"| {r['news_id']} | {r['published_at']} | {r['category']} | {r['topic_label'][:20]} | {r['manual_arc']} | {r['role']} | {title} |\n")

    print(f"Wrote {out_md}: {len(pool)} unique, {len(arc_rows)} arcs")
    return arc_rows

if __name__ == "__main__":
    main()

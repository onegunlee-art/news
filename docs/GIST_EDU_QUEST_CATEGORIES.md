# GIST EDU Quest Categories v1

> Machine-readable: [`GIST_EDU_QUEST_CATEGORIES.json`](GIST_EDU_QUEST_CATEGORIES.json)  
> Convention: `edu_daily_quests.scores.category` = category id below

## 10 upper categories

| ID | Label | Arcs |
|----|-------|------|
| `ai_tech` | AI·기술 | ARC-AI-JOBS, ARC-AI-GEOPOL, ARC-AI-SECURITY, ARC-CHIP-SUPPLY, ARC-AI-REGULATION, ARC-ENERGY-AI |
| `us_china_trade` | 미중·무역 | ARC-US-CN-TRADE, ARC-TRUMP-TARIFF, ARC-SUPPLY-CHAIN |
| `middle_east_iran` | 중동·이란 | ARC-IRAN-NUKE, ARC-IRAN-REGION, ARC-MIDEAST-CEASEFIRE |
| `east_asia_security` | 동아시아 안보 | ARC-JAPAN-DEFENSE, ARC-TAIWAN-STRAIT, ARC-DPRK-PENINSULA, ARC-KOR-DEFENSE |
| `europe_war` | 유럽·전쟁 | ARC-UKRAINE-WAR, ARC-NATO-EUROPE |
| `energy_climate` | 에너지·기후 | ARC-CLIMATE-ENERGY, ARC-OIL-GAS, ARC-ENERGY-AI |
| `global_economy` | 세계경제 | ARC-INFLATION-FED, ARC-SUPPLY-CHAIN |
| `china_industry` | 중국 산업 | ARC-EV-CHINA |
| `us_politics` | 미국 정치 | ARC-US-POLITICS, ARC-TRUMP-TARIFF |
| `society_youth` | 사회·청소년 | ARC-AI-JOBS, ARC-SOCIETY-YOUTH |

## New arcs (catalog v1)

See JSON `new_arcs_v1` for ARC-DPRK-PENINSULA, ARC-KOR-DEFENSE, ARC-MIDEAST-CEASEFIRE, ARC-NATO-EUROPE, ARC-US-POLITICS, ARC-AI-REGULATION, ARC-ENERGY-AI, ARC-SUPPLY-CHAIN, ARC-SOCIETY-YOUTH.

## Usage

```php
require_once 'public/api/edu/lib/eduQuestCatalog.php';
$cat = eduQuestCategoryForArc('ARC-IRAN-REGION'); // middle_east_iran
```

```bash
php tools/edu_quest_catalog_batch.php --dry-run --category=middle_east_iran
```

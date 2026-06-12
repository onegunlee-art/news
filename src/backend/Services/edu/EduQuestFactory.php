<?php
/**
 * GIST EDU — Article → Quest Factory (READ ONLY on news / analysis_embeddings)
 */
declare(strict_types=1);

namespace Services\Edu;

use Agents\Services\SupabaseService;
use PDO;

class EduQuestFactory
{
    private const MIN_ARTICLES = 3;
    private const MAX_ARTICLES_PER_QUEST = 5;

    /** @var array<string, array{0: string, 1: string}> */
    private const ARC_META = [
        'ARC-AI-JOBS' => [
            'AI 일자리 충격 가능성과 선제적 사회 대응 필요성에 대한 인식 공유',
            '안전망·규제 강화 vs 성장·도구 활용 자유, 인지 보존 vs 자동화 가속',
        ],
        'ARC-AI-GEOPOL' => [
            'AI 패권은 칩·인재·전력 복합 전장이며 미중 양국이 국가안보 프레임으로 경쟁',
            '미국 수출규제·동맹 vs 중국 전력·인재 규모, 군사 AI 억제 vs 가속',
        ],
        'ARC-AI-SECURITY' => [
            '자율 AI·사이버·군사 리스크와 규제 필요성 공통 인식',
            '강규제 vs 성장, 미·중 정부·기업 입장 충돌',
        ],
        'ARC-IRAN-NUKE' => [
            '이란 핵·제재가 중동 안보 핵심 변수',
            '강경 제재·군사 옵션 vs 협상·JCPOA 복원',
        ],
        'ARC-IRAN-REGION' => [
            '지역 충돌이 호르무즈·유가·공급망에 파급',
            '강경 대응 vs 외교 봉합, 유가 안보 vs 안보 현실',
        ],
        'ARC-US-CN-TRADE' => [
            '미중 경쟁이 관세·공급망·기술 표준으로 확장',
            '탈중국 비용 vs 효율, 동맹 조율 vs 자국 이익',
        ],
        'ARC-TRUMP-TARIFF' => [
            '관세를 협상·산업 보호 레버리지로 보는 프레임 공유',
            '단기 고용·제조 vs 소비자 물가·보복',
        ],
        'ARC-CLIMATE-ENERGY' => [
            '에너지 전환·기후 리스크가 경제·산업 정책 중심',
            '급진 탈탄소 vs 점진 전환, 재생 vs 가스 브릿지',
        ],
        'ARC-OIL-GAS' => [
            '에너지 공급망·지정학이 가격·안보 동시 좌우',
            'LNG·비축 확대 vs 재생 의존, AI 전력 수요 구조 변화',
        ],
        'ARC-EV-CHINA' => [
            '중국 EV 우위가 글로벌 완성차 딜레마',
            '보조금·관세 보호 vs 시장 개방',
        ],
        'ARC-CHIP-SUPPLY' => [
            '반도체가 AI·안보 병목, 동북아 집중 리스크',
            '수출규제·자국 생산 vs 글로벌 분업',
        ],
        'ARC-JAPAN-DEFENSE' => [
            '일본 방위 확대가 미일·동아시아 균형 변수',
            '안보 현실 vs 역사·여론, 경제안보 vs 군사 확장',
        ],
        'ARC-TAIWAN-STRAIT' => [
            '대만 해협이 미중 기술·군사·경제 교차점',
            '군사 억제·동맹 vs 외교적 모호성',
        ],
        'ARC-UKRAINE-WAR' => [
            '장기전이 유럽 안보·에너지에 파급',
            '지원 지속 vs 협상·동결',
        ],
        'ARC-INFLATION-FED' => [
            '물가·금리·성장 트레이드오프가 정책 중심',
            '고금리 유지 vs 경기 방어',
        ],
    ];

    /** @var array<string, list<int>> */
    private const ARC_SEED_IDS = [
        'ARC-AI-JOBS' => [507, 72, 462, 297, 288],
        'ARC-AI-GEOPOL' => [267, 248, 366, 126, 270],
        'ARC-AI-SECURITY' => [371, 126, 72, 270, 402],
        'ARC-IRAN-NUKE' => [196, 152, 238, 233, 263],
        'ARC-IRAN-REGION' => [528, 384, 132, 437, 290],
        'ARC-US-CN-TRADE' => [283, 392, 397, 119, 252],
        'ARC-TRUMP-TARIFF' => [397, 503, 375, 497, 237],
        'ARC-CLIMATE-ENERGY' => [240, 291, 93, 193, 195],
        'ARC-OIL-GAS' => [287, 496, 150, 193, 384],
        'ARC-EV-CHINA' => [459, 506, 299, 225, 392],
        'ARC-CHIP-SUPPLY' => [220, 558, 513, 532, 240],
        'ARC-JAPAN-DEFENSE' => [452, 546, 432, 433, 558],
        'ARC-TAIWAN-STRAIT' => [514, 427, 521, 119, 452],
        'ARC-UKRAINE-WAR' => [87, 437, 384, 263, 152],
        'ARC-INFLATION-FED' => [150, 210, 338, 432, 375],
    ];

    private PDO $pdo;
    private SupabaseService $supabase;
    private $llm;

    public function __construct(PDO $pdo, SupabaseService $supabase, $llmClient = null)
    {
        $this->pdo = $pdo;
        $this->supabase = $supabase;
        $this->llm = $llmClient;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function discoverCandidates(int $maxCandidates = 5, int $lookbackDays = 90): array
    {
        $usedNewsIds = $this->loadUsedNewsIds();
        $published = $this->loadPublishedArticles($lookbackDays);
        if ($published === []) {
            return [];
        }

        $topicGroups = $this->groupByTopicLabel($published);
        $candidates = [];

        foreach (self::ARC_SEED_IDS as $arcCode => $seedIds) {
            if (count($candidates) >= $maxCandidates) {
                break;
            }

            $groupIds = [];
            foreach ($seedIds as $id) {
                if (isset($published[$id]) && !isset($usedNewsIds[$id])) {
                    $groupIds[$id] = true;
                }
            }

            foreach ($published as $id => $article) {
                if (isset($usedNewsIds[$id])) {
                    continue;
                }
                $topic = strtolower(trim((string) ($article['topic_label'] ?? '')));
                foreach ($topicGroups[$arcCode] ?? [] as $hint) {
                    if ($topic !== '' && str_contains($topic, strtolower($hint))) {
                        $groupIds[$id] = true;
                        break;
                    }
                }
            }

            $articleIds = array_keys($groupIds);
            if (count($articleIds) < self::MIN_ARTICLES) {
                continue;
            }

            if ($this->arcHasRecentQuest($arcCode)) {
                continue;
            }

            $selected = $this->selectArticles($articleIds, $published);
            $draft = $this->buildDraftQuest($arcCode, $selected);
            if ($draft !== null) {
                $candidates[] = $draft;
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $draft
     * @return array{quest_id: string, quest_code: string}|null
     */
    public function persistDraft(array $draft, bool $dryRun = false): ?array
    {
        if (count($draft['articles'] ?? []) < self::MIN_ARTICLES) {
            return null;
        }

        if ($dryRun) {
            return [
                'quest_id' => 'dry-run',
                'quest_code' => (string) ($draft['quest_code'] ?? 'Q-DRY'),
            ];
        }

        $questRow = [
            'quest_code' => $draft['quest_code'],
            'quest_title' => $draft['quest_title'],
            'grade_band' => $draft['grade_band'] ?? 'high',
            'status' => 'draft',
            'manual_arc' => $draft['manual_arc'] ?? null,
            'pro_line' => $draft['pro_line'],
            'con_line' => $draft['con_line'],
            'alignment_summary' => $draft['alignment_summary'] ?? '',
            'conflict_summary' => $draft['conflict_summary'],
            'hammer_hints' => json_encode($draft['hammer_hints'] ?? [], JSON_UNESCAPED_UNICODE),
            'pilot_priority' => $draft['pilot_priority'] ?? 'C',
        ];

        $inserted = $this->supabase->insert('edu_daily_quests', $questRow);
        $questId = $inserted[0]['id'] ?? null;
        if ($questId === null) {
            return null;
        }

        $sort = 0;
        foreach ($draft['articles'] as $article) {
            $this->supabase->insert('edu_quest_articles', [
                'quest_id' => $questId,
                'news_id' => (int) $article['news_id'],
                'role' => $article['role'],
                'sort_order' => $sort++,
                'title' => $article['title'] ?? null,
                'gist_url' => $article['gist_url'] ?? ('https://www.thegist.co.kr/news/' . $article['news_id']),
                'excerpt' => $article['excerpt'] ?? null,
                'why_important' => $article['why_important'] ?? null,
                'source_outlet' => $article['source_outlet'] ?? null,
                'published_at' => $article['published_at'] ?? null,
            ]);
        }

        return [
            'quest_id' => $questId,
            'quest_code' => (string) $draft['quest_code'],
        ];
    }

    /** @return array<int, true> */
    private function loadUsedNewsIds(): array
    {
        $used = [];
        $rows = $this->supabase->select('edu_quest_articles', 'select=news_id', 5000) ?? [];
        foreach ($rows as $row) {
            $used[(int) ($row['news_id'] ?? 0)] = true;
        }
        return $used;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPublishedArticles(int $lookbackDays): array
    {
        $cols = $this->newsColumns();
        $select = ['id', 'title', 'status', 'category'];
        foreach (['narration', 'why_important', 'description', 'ai_summary', 'source', 'original_source', 'published_at'] as $c) {
            if (in_array($c, $cols, true)) {
                $select[] = $c;
            }
        }

        $since = date('Y-m-d', strtotime("-{$lookbackDays} days"));
        $sql = 'SELECT ' . implode(', ', $select)
            . ' FROM news WHERE status = ? AND published_at >= ? ORDER BY published_at DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['published', $since]);

        $articles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['id'];
            $narration = (string) ($row['narration'] ?? $row['description'] ?? '');
            $articles[$id] = [
                'news_id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'narration' => $narration,
                'why_important' => (string) ($row['why_important'] ?? ''),
                'ai_summary' => (string) ($row['ai_summary'] ?? ''),
                'source_outlet' => (string) ($row['original_source'] ?? $row['source'] ?? 'the gist'),
                'published_at' => $row['published_at'] ?? null,
                'gist_url' => 'https://www.thegist.co.kr/news/' . $id,
                'excerpt' => mb_substr($narration, 0, 300),
                'topic_label' => '',
            ];
        }

        $this->enrichTopicLabels($articles);
        return $articles;
    }

    /** @param array<int, array<string, mixed>> $articles */
    private function enrichTopicLabels(array &$articles): void
    {
        if (!$this->supabase->isConfigured() || $articles === []) {
            return;
        }

        $ids = array_keys($articles);
        $chunks = array_chunk($ids, 50);
        foreach ($chunks as $chunk) {
            $filter = 'news_id=in.(' . implode(',', $chunk) . ')&chunk_type=eq.published';
            $rows = $this->supabase->select('analysis_embeddings', $filter, 200) ?? [];
            foreach ($rows as $row) {
                $nid = (int) ($row['news_id'] ?? 0);
                if (!isset($articles[$nid])) {
                    continue;
                }
                $meta = $row['metadata'] ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?: [];
                }
                $label = trim((string) ($meta['topic_label'] ?? ''));
                if ($label !== '') {
                    $articles[$nid]['topic_label'] = $label;
                }
            }
        }
    }

    /** @return array<string, list<string>> */
    private function groupByTopicLabel(array $published): array
    {
        $hints = [];
        foreach (self::ARC_SEED_IDS as $arc => $ids) {
            $words = [];
            foreach ($ids as $id) {
                if (!isset($published[$id])) {
                    continue;
                }
                $label = $published[$id]['topic_label'] ?? '';
                if ($label !== '') {
                    $words[] = mb_substr($label, 0, 12);
                }
            }
            $hints[$arc] = array_values(array_unique(array_filter($words)));
        }
        return $hints;
    }

    private function arcHasRecentQuest(string $arcCode): bool
    {
        $since = date('c', strtotime('-14 days'));
        $rows = $this->supabase->select(
            'edu_daily_quests',
            'manual_arc=eq.' . rawurlencode($arcCode)
                . '&status=in.(draft,approved)'
                . '&created_at=gte.' . rawurlencode($since),
            1
        ) ?? [];
        return !empty($rows[0]);
    }

    /**
     * @param list<int> $ids
     * @param array<int, array<string, mixed>> $published
     * @return list<array<string, mixed>>
     */
    private function selectArticles(array $ids, array $published): array
    {
        usort($ids, static function (int $a, int $b) use ($published): int {
            $da = strtotime((string) ($published[$a]['published_at'] ?? '1970-01-01'));
            $db = strtotime((string) ($published[$b]['published_at'] ?? '1970-01-01'));
            return $db <=> $da;
        });

        $ids = array_slice($ids, 0, self::MAX_ARTICLES_PER_QUEST);
        $roles = ['primary', 'context', 'context', 'counter', 'counter'];
        $selected = [];
        foreach ($ids as $i => $id) {
            if (!isset($published[$id])) {
                continue;
            }
            $selected[] = array_merge($published[$id], [
                'role' => $roles[$i] ?? 'context',
            ]);
        }
        return $selected;
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return array<string, mixed>|null
     */
    private function buildDraftQuest(string $arcCode, array $articles): ?array
    {
        if (count($articles) < self::MIN_ARTICLES) {
            return null;
        }

        [$alignment, $conflict] = self::ARC_META[$arcCode] ?? ['', ''];
        $llmFields = $this->generateQuestFields($arcCode, $articles, $alignment, $conflict);

        $proLine = $llmFields['pro_line'] ?? '찬성 입장';
        $conLine = $llmFields['con_line'] ?? '반대 입장';
        $title = $llmFields['quest_title'] ?? ($articles[0]['title'] ?? '오늘의 퀘스트') . '?';

        return [
            'quest_code' => 'Q-AUTO-' . date('ymd') . '-' . strtoupper(substr(md5($arcCode . time()), 0, 4)),
            'quest_title' => $title,
            'grade_band' => $llmFields['grade_band'] ?? 'high',
            'manual_arc' => $arcCode,
            'pro_line' => $proLine,
            'con_line' => $conLine,
            'alignment_summary' => $llmFields['alignment_summary'] ?? $alignment,
            'conflict_summary' => $llmFields['conflict_summary'] ?? $conflict,
            'hammer_hints' => [
                'pro' => $llmFields['hammer_hint_pro'] ?? $conLine,
                'con' => $llmFields['hammer_hint_con'] ?? $proLine,
            ],
            'pilot_priority' => 'C',
            'articles' => $articles,
        ];
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return array<string, string>
     */
    private function generateQuestFields(string $arcCode, array $articles, string $alignment, string $conflict): array
    {
        if ($this->llm === null) {
            return [];
        }

        $lines = [];
        foreach ($articles as $a) {
            $lines[] = sprintf(
                '- [%s] %s | %s',
                $a['role'] ?? 'context',
                $a['title'] ?? '',
                mb_substr((string) ($a['why_important'] ?? $a['excerpt'] ?? ''), 0, 120)
            );
        }
        $articleBlock = implode("\n", $lines);

        $system = <<<PROMPT
너는 GIST EDU 퀘스트 큐레이터야. 학생 토론용 퀘스트 필드를 JSON으로만 출력해.

{
  "quest_title": "물음표로 끝나는 질문형 제목",
  "pro_line": "찬성 입장 한 줄",
  "con_line": "반대 입장 한 줄",
  "alignment_summary": "기사들의 공통 인식 2문장",
  "conflict_summary": "기사들의 갈등축 2문장",
  "hammer_hint_pro": "찬성 입장에 대한 반론 힌트 한 줄",
  "hammer_hint_con": "반대 입장에 대한 반론 힌트 한 줄",
  "grade_band": "middle 또는 high"
}
PROMPT;

        $user = "arc: {$arcCode}\n일치(참고): {$alignment}\n불일치(참고): {$conflict}\n\n기사:\n{$articleBlock}";

        $response = $this->llm->haiku($system, [['role' => 'user', 'content' => $user]]);
        if (!empty($response['error'])) {
            return [];
        }

        $content = (string) ($response['content'] ?? '');
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $parsed = json_decode($m[0], true);
            return is_array($parsed) ? $parsed : [];
        }
        return [];
    }

    /** @return list<string> */
    private function newsColumns(): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }
        $cols = [];
        foreach ($this->pdo->query('SHOW COLUMNS FROM news') as $row) {
            $cols[] = (string) $row['Field'];
        }
        return $cols;
    }
}

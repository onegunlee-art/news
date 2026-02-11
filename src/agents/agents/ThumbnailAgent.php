<?php
/**
 * Thumbnail Agent v3.0
 *
 * 뉴스 기사 썸네일 생성 에이전트.
 * GPT가 기사를 분석하여 등장인물/국가/상황을 추출한 뒤,
 * 최적의 DALL·E 3 프롬프트를 자동 생성합니다.
 *
 * 우선순위: GPT→DALL·E 3 생성 → 원본 og:image → 카테고리 플레이스홀더
 *
 * @package Agents\Agents
 * @author The Gist AI System
 * @version 3.0.0
 */

declare(strict_types=1);

namespace Agents\Agents;

use Agents\Core\BaseAgent;
use Agents\Models\AgentContext;
use Agents\Models\AgentResult;
use Agents\Models\ArticleData;
use Agents\Services\OpenAIService;

class ThumbnailAgent extends BaseAgent
{
    private ?string $projectRoot = null;

    /** 인물 → 시각적 심볼 매핑 (실제 얼굴 대신 상징물) */
    private const FIGURE_SYMBOLS = [
        // 미국
        'trump'     => 'a figure with distinctive golden hair silhouette behind a podium with American flag',
        '트럼프'    => 'a figure with distinctive golden hair silhouette behind a podium with American flag',
        'biden'     => 'an elderly statesman silhouette at the White House',
        '바이든'    => 'an elderly statesman silhouette at the White House',
        'harris'    => 'a female leader silhouette at a government podium',
        '해리스'    => 'a female leader silhouette at a government podium',
        // 중국
        'xi jinping' => 'a leader silhouette before the Great Hall of the People with Chinese flag',
        '시진핑'     => 'a leader silhouette before the Great Hall of the People with Chinese flag',
        // 러시아
        'putin'     => 'a stern leader silhouette before the Kremlin with Russian tricolor',
        '푸틴'      => 'a stern leader silhouette before the Kremlin with Russian tricolor',
        // 북한
        'kim jong'  => 'a military leader silhouette with North Korean iconography',
        '김정은'    => 'a military leader silhouette with North Korean iconography',
        // 우크라이나
        'zelensky'  => 'a wartime leader silhouette in military green with Ukrainian blue-yellow',
        '젤렌스키'  => 'a wartime leader silhouette in military green with Ukrainian blue-yellow',
        // 유럽
        'macron'    => 'a leader silhouette before the Élysée Palace with French tricolor',
        '마크롱'    => 'a leader silhouette before the Élysée Palace with French tricolor',
        'scholz'    => 'a leader silhouette before the Bundestag with German flag',
        'starmer'   => 'a leader silhouette before 10 Downing Street with Union Jack',
        // 중동
        'netanyahu' => 'a leader silhouette before the Knesset with Israeli flag',
        '네타냐후'  => 'a leader silhouette before the Knesset with Israeli flag',
        'mbs'       => 'a Saudi prince silhouette with Arabian palace and Saudi flag',
        // 일본/한국
        '윤석열'    => 'a leader silhouette before the Blue House with Korean flag',
        'kishida'   => 'a leader silhouette before the Diet building with Japanese flag',
        '기시다'    => 'a leader silhouette before the Diet building with Japanese flag',
    ];

    /** 국가/지역 → 시각적 랜드마크 & 색상 */
    private const COUNTRY_VISUALS = [
        'united states' => 'Washington DC skyline, Capitol dome, stars-and-stripes palette',
        'america' => 'Washington DC skyline, Capitol dome, stars-and-stripes palette',
        '미국' => 'Washington DC skyline, Capitol dome, stars-and-stripes palette',
        'china' => 'Beijing skyline, Great Wall silhouette, red and gold palette',
        '중국' => 'Beijing skyline, Great Wall silhouette, red and gold palette',
        'russia' => 'Kremlin towers, St. Basil domes, white-blue-red palette',
        '러시아' => 'Kremlin towers, St. Basil domes, white-blue-red palette',
        'ukraine' => 'Ukrainian wheat field under blue sky, blue-yellow palette',
        '우크라이나' => 'Ukrainian wheat field under blue sky, blue-yellow palette',
        'north korea' => 'Pyongyang monuments, military parade, austere palette',
        '북한' => 'Pyongyang monuments, military parade, austere palette',
        'japan' => 'Tokyo skyline with Mount Fuji, rising sun motif',
        '일본' => 'Tokyo skyline with Mount Fuji, rising sun motif',
        'korea' => 'Seoul skyline with Gwanghwamun gate, Korean flag colors',
        '한국' => 'Seoul skyline with Gwanghwamun gate, Korean flag colors',
        'europe' => 'European Parliament, EU stars circle, continental map',
        '유럽' => 'European Parliament, EU stars circle, continental map',
        'eu' => 'European Parliament, EU stars circle on blue',
        'nato' => 'NATO compass rose emblem, alliance flags',
        'middle east' => 'desert landscape with domed architecture, warm tones',
        '중동' => 'desert landscape with domed architecture, warm tones',
        'israel' => 'Jerusalem skyline, Star of David motif, blue-white',
        '이스라엘' => 'Jerusalem skyline, Star of David motif, blue-white',
        'iran' => 'Isfahan domes, Persian architecture, green-white-red',
        '이란' => 'Isfahan domes, Persian architecture, green-white-red',
        'gaza' => 'war-torn urban landscape, olive branch symbolism',
        '가자' => 'war-torn urban landscape, olive branch symbolism',
        'saudi' => 'Riyadh skyline, Arabian architecture, green and gold',
        '사우디' => 'Riyadh skyline, Arabian architecture, green and gold',
        'india' => 'New Delhi landmarks, Taj Mahal silhouette, saffron-white-green',
        '인도' => 'New Delhi landmarks, Taj Mahal silhouette, saffron-white-green',
        'taiwan' => 'Taipei 101 skyline, strait symbolism',
        '대만' => 'Taipei 101 skyline, strait symbolism',
        'africa' => 'African continental silhouette, diverse landscape',
        '아프리카' => 'African continental silhouette, diverse landscape',
        'brazil' => 'Christ the Redeemer silhouette, green-yellow palette',
        'uk' => 'Westminster Palace, Big Ben silhouette, Union Jack colors',
        '영국' => 'Westminster Palace, Big Ben silhouette, Union Jack colors',
        'france' => 'Eiffel Tower silhouette, Arc de Triomphe, tricolor palette',
        '프랑스' => 'Eiffel Tower silhouette, Arc de Triomphe, tricolor palette',
        'germany' => 'Brandenburg Gate, Bundestag dome, black-red-gold',
        '독일' => 'Brandenburg Gate, Bundestag dome, black-red-gold',
    ];

    /** 상황/이벤트 → 시각적 메타포 */
    private const SITUATION_VISUALS = [
        'war' => 'dramatic battlefield atmosphere with smoke and shattered structures, dark ominous sky',
        '전쟁' => 'dramatic battlefield atmosphere with smoke and shattered structures, dark ominous sky',
        'conflict' => 'opposing forces represented by chess pieces in confrontation',
        '분쟁' => 'opposing forces represented by chess pieces in confrontation',
        'invasion' => 'military vehicles advancing across a border, barbed wire, red alert tones',
        '침공' => 'military vehicles advancing across a border, barbed wire, red alert tones',
        'missile' => 'missile trajectory arcs across a night sky, radar displays',
        '미사일' => 'missile trajectory arcs across a night sky, radar displays',
        'nuclear' => 'atomic symbol glowing ominously, hazard warning tones',
        '핵' => 'atomic symbol glowing ominously, hazard warning tones',
        'summit' => 'two leaders shaking hands as silhouettes, conference table, flags behind them',
        '정상회담' => 'two leaders shaking hands as silhouettes, conference table, flags behind them',
        'diplomacy' => 'handshake silhouette over a world map, olive branches',
        '외교' => 'handshake silhouette over a world map, olive branches',
        'sanction' => 'broken chain links over currency symbols, red prohibition signs',
        '제재' => 'broken chain links over currency symbols, red prohibition signs',
        'tariff' => 'shipping containers stacked with price tags, trade barrier walls',
        '관세' => 'shipping containers stacked with price tags, trade barrier walls',
        'trade' => 'cargo ships and global trade routes on a world map',
        '무역' => 'cargo ships and global trade routes on a world map',
        'election' => 'ballot box, voting hands, democratic symbols, campaign podiums',
        '선거' => 'ballot box, voting hands, democratic symbols, campaign podiums',
        'economy' => 'stock market charts, currency symbols, financial district skyline',
        '경제' => 'stock market charts, currency symbols, financial district skyline',
        'recession' => 'downward trending graph, crumbling buildings, storm clouds',
        '불황' => 'downward trending graph, crumbling buildings, storm clouds',
        'inflation' => 'rising price tags, shrinking shopping cart, soaring arrows',
        '인플레이션' => 'rising price tags, shrinking shopping cart, soaring arrows',
        'interest rate' => 'central bank building with percentage symbols, scales balancing',
        '금리' => 'central bank building with percentage symbols, scales balancing',
        'oil' => 'oil derricks, crude barrels, pipeline infrastructure, petrodollar',
        '석유' => 'oil derricks, crude barrels, pipeline infrastructure, petrodollar',
        'climate' => 'melting ice, rising sea levels, wind turbines, Earth thermometer',
        '기후' => 'melting ice, rising sea levels, wind turbines, Earth thermometer',
        'ai' => 'neural network nodes, digital brain, circuit board patterns, blue glow',
        '인공지능' => 'neural network nodes, digital brain, circuit board patterns, blue glow',
        'semiconductor' => 'microchip close-up, silicon wafer, circuit traces glowing',
        '반도체' => 'microchip close-up, silicon wafer, circuit traces glowing',
        'cyber' => 'digital lock, matrix code rain, cyber shield glowing',
        '사이버' => 'digital lock, matrix code rain, cyber shield glowing',
        'space' => 'rocket launch, orbital station, stars and planets',
        '우주' => 'rocket launch, orbital station, stars and planets',
        'protest' => 'raised fists silhouettes, banners, crowd in motion',
        '시위' => 'raised fists silhouettes, banners, crowd in motion',
        'refugee' => 'migration routes on map, silhouetted families walking, dawn light',
        '난민' => 'migration routes on map, silhouetted families walking, dawn light',
        'terrorism' => 'shattered glass, security perimeter, emergency lights',
        '테러' => 'shattered glass, security perimeter, emergency lights',
        'pandemic' => 'virus molecules, medical shields, global spread map',
        '팬데믹' => 'virus molecules, medical shields, global spread map',
        'alliance' => 'interlocking shields, multiple flags united, handshake over map',
        '동맹' => 'interlocking shields, multiple flags united, handshake over map',
    ];

    public function __construct(OpenAIService $openai, array $config = [])
    {
        parent::__construct($openai, $config);
        $this->projectRoot = $config['project_root'] ?? null;
    }

    public function getName(): string
    {
        return 'ThumbnailAgent';
    }

    protected function getDefaultPrompts(): array
    {
        return [
            'system' => 'Thumbnail image selection agent.',
            'tasks' => [],
        ];
    }

    public function validate(mixed $input): bool
    {
        if ($input instanceof AgentContext) {
            return $input->getArticleData() !== null;
        }
        return false;
    }

    // ══════════════════════════════════════════════════════════
    //  프롬프트 생성 (핵심)
    // ══════════════════════════════════════════════════════════

    /**
     * GPT를 활용한 2단계 프롬프트 생성.
     * Step 1: GPT가 기사를 분석하여 최적의 DALL-E 프롬프트를 작성
     * Step 2: 해당 프롬프트를 DALL-E에 전달
     *
     * GPT 호출 실패 시 규칙 기반 프롬프트로 폴백.
     */
    private function buildSmartImagePrompt(string $title, string $description, string $category): string
    {
        // GPT에게 DALL-E 프롬프트 작성을 요청
        if ($this->openai->isConfigured()) {
            try {
                $articleContext = trim($title . "\n" . mb_substr($description, 0, 400));

                $systemPrompt = <<<SYS
You are an expert editorial illustration art director for an international news magazine called "The Gist".

Your job: Given a news article headline and summary, create a DALL-E 3 image prompt that produces a stunning, magazine-quality editorial illustration.

RULES:
1. NEVER include any text, letters, words, or numbers in the image
2. NEVER depict realistic human faces - use silhouettes, abstract figures, or symbolic representations
3. DO include: national landmarks, flags (as design elements), geographic features, symbolic objects
4. DO convey the geopolitical situation, power dynamics, and emotional tone of the story
5. Use cinematic composition with strong foreground/background separation
6. Specify a rich color palette that matches the mood (warm for conflict, cool for diplomacy, etc.)
7. Keep it under 200 words
8. Write in English only
9. Begin directly with the scene description (no preamble)

STYLE GUIDELINES:
- Think: The Economist cover illustrations meets Bloomberg Businessweek
- Sophisticated editorial art: NOT clip-art, NOT cartoon, NOT photorealistic
- Rich textures, dramatic lighting, layered composition
- Metaphorical visual storytelling (chess pieces for strategy, scales for balance of power, etc.)
SYS;

                $userPrompt = "Create a DALL-E 3 prompt for this news article:\n\n{$articleContext}";

                $gptPrompt = $this->openai->chat(
                    systemPrompt: $systemPrompt,
                    userPrompt: $userPrompt,
                    options: [
                        'model' => 'gpt-4o-mini',
                        'max_tokens' => 300,
                        'temperature' => 0.8,
                        'timeout' => 20,
                    ]
                );

                if (!empty(trim($gptPrompt))) {
                    $this->log('GPT-generated DALL-E prompt: ' . mb_substr($gptPrompt, 0, 100) . '...', 'info');
                    return trim($gptPrompt);
                }
            } catch (\Throwable $e) {
                $this->log('GPT prompt generation failed, using rule-based: ' . $e->getMessage(), 'warning');
            }
        }

        // GPT 실패 시 → 규칙 기반 프롬프트
        return $this->buildRuleBasedPrompt($title, $description, $category);
    }

    /**
     * 규칙 기반 프롬프트 생성 (GPT 폴백).
     * 기사에서 인물/국가/상황 키워드를 추출하여 시각 요소를 조합.
     */
    private function buildRuleBasedPrompt(string $title, string $description, string $category): string
    {
        $combined = mb_strtolower($title . ' ' . mb_substr($description, 0, 300));
        $elements = [];

        // ── 1. 인물 감지 → 상징적 실루엣 ──
        $figuresFound = [];
        foreach (self::FIGURE_SYMBOLS as $keyword => $visual) {
            if (mb_strpos($combined, mb_strtolower($keyword)) !== false) {
                $figuresFound[] = $visual;
                if (count($figuresFound) >= 2) break; // 최대 2인물
            }
        }

        // ── 2. 국가/지역 감지 → 랜드마크 & 팔레트 ──
        $countriesFound = [];
        foreach (self::COUNTRY_VISUALS as $keyword => $visual) {
            if (mb_strpos($combined, mb_strtolower($keyword)) !== false) {
                $countriesFound[] = $visual;
                if (count($countriesFound) >= 2) break;
            }
        }

        // ── 3. 상황/이벤트 감지 → 시각적 메타포 ──
        $situationsFound = [];
        foreach (self::SITUATION_VISUALS as $keyword => $visual) {
            if (mb_strpos($combined, mb_strtolower($keyword)) !== false) {
                $situationsFound[] = $visual;
                if (count($situationsFound) >= 2) break;
            }
        }

        // ── 구성 ──
        $prompt = 'A sophisticated editorial illustration for an international news magazine. ';
        $prompt .= 'Style: rich editorial art with cinematic lighting, layered composition, deep textures. ';
        $prompt .= 'Dark navy background (#0f172a). NO text, NO letters, NO realistic human faces. ';

        if (!empty($figuresFound)) {
            $elements[] = 'Figures: ' . implode('; ', $figuresFound);
        }
        if (!empty($countriesFound)) {
            $elements[] = 'Setting: ' . implode('; ', $countriesFound);
        }
        if (!empty($situationsFound)) {
            $elements[] = 'Situation: ' . implode('; ', $situationsFound);
        }

        if (!empty($elements)) {
            $prompt .= 'Scene elements — ' . implode('. ', $elements) . '. ';
        }

        // 카테고리별 분위기 보정
        $mood = match ($category) {
            'diplomacy', 'politics' => 'Mood: authoritative, geopolitical gravitas, cool blue tones with gold accents.',
            'economy', 'finance'    => 'Mood: dynamic, financial energy, emerald-green and silver palette.',
            'entertainment'         => 'Mood: vibrant, culturally rich, warm orange and magenta palette.',
            'technology', 'tech'    => 'Mood: futuristic, innovation, electric blue and violet palette.',
            'security', 'military'  => 'Mood: intense, strategic tension, crimson and dark steel palette.',
            default                 => 'Mood: professional, impactful, sophisticated neutral tones.',
        };
        $prompt .= $mood;

        // 요소가 전혀 감지 안 됐으면 제목 자체를 영감으로 추가
        if (empty($elements)) {
            $titleSnippet = mb_substr($title, 0, 200);
            $prompt .= " Inspired by the news topic: {$titleSnippet}";
        }

        return $prompt;
    }

    // ══════════════════════════════════════════════════════════
    //  유틸리티
    // ══════════════════════════════════════════════════════════

    /**
     * 원본 og:image URL이 유효한지 확인.
     */
    private function isValidOriginalImage(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }
        if (str_contains($url, 'placehold.co')) {
            return false;
        }
        if (str_starts_with($url, 'data:')) {
            return false;
        }
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }
        return true;
    }

    /**
     * 카테고리/제목 기반 의미 있는 플레이스홀더 URL 생성
     */
    private function buildFallbackPlaceholder(string $title, string $category): string
    {
        $themes = [
            'diplomacy' => ['0f172a', '38bdf8', 'Diplomacy'],
            'economy'   => ['0f172a', '34d399', 'Economy'],
            'entertainment' => ['0f172a', 'fb923c', 'Entertainment'],
            'technology' => ['0f172a', 'a78bfa', 'Tech'],
            'security'  => ['0f172a', 'f87171', 'Security'],
        ];

        $theme = $themes[$category] ?? ['1e293b', '94a3b8', 'The+Gist'];
        $bg = $theme[0];
        $fg = $theme[1];
        $label = $theme[2];

        return "https://placehold.co/800x500/{$bg}/{$fg}?text={$label}";
    }

    // ══════════════════════════════════════════════════════════
    //  메인 프로세스
    // ══════════════════════════════════════════════════════════

    public function process(AgentContext $context): AgentResult
    {
        $this->ensureInitialized();

        $article = $context->getArticleData();
        if ($article === null) {
            return AgentResult::failure(
                '기사 데이터가 없습니다. ValidationAgent를 먼저 실행하세요.',
                $this->getName()
            );
        }

        $title = $article->getTitle();
        $description = $article->getDescription() ?? '';
        $category = $article->getMetadata()['category'] ?? '';
        $originalImageUrl = $article->getImageUrl();

        $newImageUrl = null;
        $thumbnailSource = 'none';
        $usedPrompt = '';

        // ── 1) GPT → DALL·E 3 생성 (최우선) ──
        if ($this->openai->isConfigured()) {
            try {
                $prompt = $this->buildSmartImagePrompt($title, $description, $category);
                $usedPrompt = $prompt;
                $generated = $this->openai->createImage($prompt);
                if ($generated !== null && $generated !== '') {
                    $newImageUrl = $generated;
                    $thumbnailSource = 'dall-e-3';
                    $this->log('Thumbnail generated with DALL·E 3: ' . $newImageUrl, 'info');
                }
            } catch (\Throwable $e) {
                $this->log('DALL·E 3 thumbnail generation failed: ' . $e->getMessage(), 'warning');
            }
        } else {
            $this->log('OpenAI not configured for DALL·E, trying fallbacks', 'info');
        }

        // ── 2) DALL-E 실패 시 → 원본 기사의 og:image 사용 ──
        if (($newImageUrl === null || $newImageUrl === '') && $this->isValidOriginalImage($originalImageUrl)) {
            $newImageUrl = $originalImageUrl;
            $thumbnailSource = 'og:image';
            $this->log('Using original og:image: ' . $originalImageUrl, 'info');
        }

        // ── 3) og:image도 없으면 → 카테고리 기반 플레이스홀더 ──
        if ($newImageUrl === null || $newImageUrl === '') {
            $newImageUrl = $this->buildFallbackPlaceholder($title, $category);
            $thumbnailSource = 'placeholder';
            $this->log('Using category placeholder for: ' . mb_substr($title, 0, 50), 'info');
        }

        $updatedArticle = $article->withImageUrl($newImageUrl);
        $this->log("Thumbnail set ({$thumbnailSource}) for: " . mb_substr($title, 0, 50) . "...", 'info');

        return AgentResult::success(
            [
                'article' => $updatedArticle->toArray(),
                'thumbnail' => [
                    'image_url' => $newImageUrl,
                    'source' => $thumbnailSource,
                    'style' => $thumbnailSource === 'dall-e-3' ? 'illustration' : ($thumbnailSource === 'og:image' ? 'original' : 'placeholder'),
                    'prompt_used' => $thumbnailSource === 'dall-e-3' ? mb_substr($usedPrompt, 0, 500) : null,
                ],
            ],
            ['agent' => $this->getName()]
        );
    }
}

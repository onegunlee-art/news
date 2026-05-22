<?php
declare(strict_types=1);

namespace App\Services;

use Agents\Services\OpenAIService;
use App\Config\IntelligenceTaxonomy;

class CategorizerService
{
    private OpenAIService $openai;

    public function __construct(?OpenAIService $openai = null)
    {
        $this->openai = $openai ?? new OpenAIService([]);
    }

    public function categorize(string $title, string $lead): array
    {
        if (!$this->openai->isConfigured()) {
            return $this->fallback($title, $lead);
        }

        $regions = implode(', ', IntelligenceTaxonomy::REGIONS);
        $topics = implode(', ', IntelligenceTaxonomy::TOPICS);
        $events = implode(', ', IntelligenceTaxonomy::EVENT_TYPES);

        $system = 'Classify geopolitical news using ONLY allowed enum values. Return valid JSON only.';
        $user = <<<PROMPT
ALLOWED VALUES:
- region: {{$regions}}
- topic: {{$topics}}
- event_type: {{$events}}

RULES:
1. Select 1-3 regions maximum
2. Select 1-3 topics maximum
3. Select exactly 1 event_type
4. DO NOT create new categories
5. relevance_score: 0-100 geopolitical relevance

Return JSON:
{"region":[],"topic":[],"event_type":"","entities":{"countries":[],"leaders":[],"orgs":[]},"relevance_score":0}

Title: {$title}
Lead: {$lead}
PROMPT;

        try {
            $raw = $this->openai->chat($system, $user, [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.1,
                'max_tokens' => 400,
                'json_mode' => true,
                'timeout' => 30,
            ]);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return $this->fallback($title, $lead);
            }
            return $this->sanitize($data);
        } catch (\Throwable $e) {
            error_log('CategorizerService error: ' . $e->getMessage());
            return $this->fallback($title, $lead);
        }
    }

    private function sanitize(array $data): array
    {
        $regions = IntelligenceTaxonomy::filterRegions((array) ($data['region'] ?? []));
        $topics = IntelligenceTaxonomy::filterTopics((array) ($data['topic'] ?? []));
        $eventType = (string) ($data['event_type'] ?? 'incident');
        if (!IntelligenceTaxonomy::isValidEventType($eventType)) {
            $eventType = 'incident';
        }
        $entities = $data['entities'] ?? [];
        if (!is_array($entities)) {
            $entities = [];
        }
        $score = max(0, min(100, (int) ($data['relevance_score'] ?? 50)));
        return [
            'region' => array_slice($regions, 0, 3),
            'topic' => array_slice($topics, 0, 3),
            'event_type' => $eventType,
            'entities' => [
                'countries' => array_values(array_slice((array) ($entities['countries'] ?? []), 0, 10)),
                'leaders' => array_values(array_slice((array) ($entities['leaders'] ?? []), 0, 10)),
                'orgs' => array_values(array_slice((array) ($entities['orgs'] ?? []), 0, 10)),
            ],
            'relevance_score' => $score,
        ];
    }

    private function fallback(string $title, string $lead): array
    {
        return [
            'region' => ['GLOBAL'],
            'topic' => ['diplomacy'],
            'event_type' => 'incident',
            'entities' => ['countries' => [], 'leaders' => [], 'orgs' => []],
            'relevance_score' => 50,
        ];
    }
}

<?php
/**
 * GPT Persona Service
 *
 * Playground에서 정의한 페르소나(system prompt)를 DB에서 조회하여 AnalysisAgent에 제공.
 *
 * @package Agents\Services
 */

declare(strict_types=1);

namespace Agents\Services;

class PersonaService
{
    private SupabaseService $supabase;
    private ?array $activePersonaCache = null;
    private static string $defaultSystemPrompt = '당신은 "The Gist"의 수석 에디터입니다. 모든 기사는 지스터(The Gist 독자)를 위한 콘텐츠입니다. 지스터는 해외 뉴스를 한국어로 이해하고 싶어하는 독자층이며, The Gist의 핵심 독자입니다. 반드시 지스터 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.';

    public function __construct(?SupabaseService $supabase = null)
    {
        $this->supabase = $supabase ?? new SupabaseService([]);
    }

    /**
     * 활성 페르소나 1건 조회 (캐시: 요청당 1회)
     */
    public function getActivePersona(): ?array
    {
        if ($this->activePersonaCache !== null) {
            return $this->activePersonaCache;
        }

        if (!$this->supabase->isConfigured()) {
            return null;
        }

        $rows = $this->supabase->select('gpt_personas', 'is_active=eq.true', 1);
        if (!is_array($rows) || empty($rows)) {
            return null;
        }

        $this->activePersonaCache = $rows[0];
        return $this->activePersonaCache;
    }

    /**
     * 서비스에 사용할 system prompt 반환.
     * DB에 활성 페르소나가 있으면 사용, 없으면 기본값.
     */
    public function getSystemPrompt(): string
    {
        $persona = $this->getActivePersona();
        if ($persona !== null && !empty(trim($persona['system_prompt'] ?? ''))) {
            return trim($persona['system_prompt']);
        }
        return self::$defaultSystemPrompt;
    }

    /**
     * 페르소나 저장 (기존 active는 false로 변경 후 새로 INSERT)
     */
    public function savePersona(string $name, string $systemPrompt, array $meta = []): ?array
    {
        if (!$this->supabase->isConfigured()) {
            return null;
        }

        $rows = $this->supabase->select('gpt_personas', 'is_active=eq.true', 100);
        if (is_array($rows) && !empty($rows)) {
            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                if ($id) {
                    $this->supabase->update('gpt_personas', "id=eq.{$id}", [
                        'is_active' => false,
                        'updated_at' => date('c'),
                    ]);
                }
            }
        }

        $inserted = $this->supabase->insert('gpt_personas', [
            'name' => $name,
            'system_prompt' => $systemPrompt,
            'meta' => $meta,
            'is_active' => true,
            'updated_at' => date('c'),
        ]);

        $this->activePersonaCache = null;

        if (is_array($inserted) && !empty($inserted)) {
            return is_array($inserted[0] ?? null) ? $inserted[0] : $inserted;
        }
        return $inserted;
    }

    /**
     * 모든 페르소나 목록 조회
     */
    public function listPersonas(int $limit = 20): array
    {
        if (!$this->supabase->isConfigured()) {
            return [];
        }

        $rows = $this->supabase->select('gpt_personas', 'order=created_at.desc', $limit);
        return is_array($rows) ? $rows : [];
    }

    /**
     * 특정 페르소나를 활성으로 설정
     */
    public function setActive(string $personaId): bool
    {
        if (!$this->supabase->isConfigured()) {
            return false;
        }

        $rows = $this->supabase->select('gpt_personas', '', 100);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                if ($id) {
                    $this->supabase->update('gpt_personas', "id=eq.{$id}", [
                        'is_active' => ($id === $personaId),
                        'updated_at' => date('c'),
                    ]);
                }
            }
        }

        $this->activePersonaCache = null;
        return true;
    }
}

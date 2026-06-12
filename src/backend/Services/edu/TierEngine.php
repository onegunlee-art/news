<?php
/**
 * GIST EDU — Tier Engine
 * Bronze → Silver → Gold 승급 로직
 * 
 * 파일럿 게이트:
 * - bronze → silver: 250점 + 완료 5회
 * - silver → gold: 700점 + 입장수정 5회
 */
declare(strict_types=1);

namespace Services\Edu;

class TierEngine
{
    private $supabase;
    
    const TIER_ORDER = ['bronze', 'silver', 'gold'];
    
    const TIER_GATES = [
        'bronze' => [
            'next' => 'silver',
            'xp_required' => 250,
            'quests_completed' => 5,
            'stance_changes' => 0,
        ],
        'silver' => [
            'next' => 'gold',
            'xp_required' => 700,
            'quests_completed' => 10,
            'stance_changes' => 5,
        ],
        'gold' => [
            'next' => null,
            'xp_required' => null,
            'quests_completed' => null,
            'stance_changes' => null,
        ],
    ];

    const TIER_LABELS = [
        'bronze' => ['en' => 'Bronze Thinker', 'ko' => '브론즈 사상가'],
        'silver' => ['en' => 'Silver Thinker', 'ko' => '실버 사상가'],
        'gold' => ['en' => 'Gold Thinker', 'ko' => '골드 사상가'],
    ];

    public function __construct($supabaseService)
    {
        $this->supabase = $supabaseService;
    }

    public function checkPromotion(string $studentId): ?array
    {
        $tier = $this->supabase->select('edu_user_tier', 'student_id=eq.' . $studentId, 1);
        if (empty($tier[0])) return null;
        
        $currentTier = $tier[0]['tier_id'] ?? 'bronze';
        $currentXp = (int)($tier[0]['xp_current'] ?? 0);
        
        if (!isset(self::TIER_GATES[$currentTier])) {
            $currentTier = 'bronze';
        }
        
        $gate = self::TIER_GATES[$currentTier];
        $nextTier = $gate['next'];
        
        if ($nextTier === null) {
            return [
                'can_promote' => false,
                'reason' => 'Already at max tier',
                'current_tier' => $currentTier,
            ];
        }
        
        $questsCompleted = $this->countCompletedQuests($studentId);
        $stanceChanges = $this->countStanceChanges($studentId);
        
        $meetsXp = $currentXp >= $gate['xp_required'];
        $meetsQuests = $questsCompleted >= $gate['quests_completed'];
        $meetsStanceChanges = $stanceChanges >= $gate['stance_changes'];
        
        $canPromote = $meetsXp && $meetsQuests && $meetsStanceChanges;
        
        $missing = [];
        if (!$meetsXp) $missing[] = "XP {$currentXp}/{$gate['xp_required']}";
        if (!$meetsQuests) $missing[] = "퀘스트 {$questsCompleted}/{$gate['quests_completed']}";
        if ($gate['stance_changes'] > 0 && !$meetsStanceChanges) {
            $missing[] = "입장수정 {$stanceChanges}/{$gate['stance_changes']}";
        }
        
        return [
            'can_promote' => $canPromote,
            'current_tier' => $currentTier,
            'next_tier' => $nextTier,
            'progress' => [
                'xp' => ['current' => $currentXp, 'required' => $gate['xp_required'], 'met' => $meetsXp],
                'quests' => ['current' => $questsCompleted, 'required' => $gate['quests_completed'], 'met' => $meetsQuests],
                'stance_changes' => ['current' => $stanceChanges, 'required' => $gate['stance_changes'], 'met' => $meetsStanceChanges],
            ],
            'missing' => $missing,
        ];
    }

    public function promote(string $studentId): array
    {
        $check = $this->checkPromotion($studentId);
        if (!$check || !$check['can_promote']) {
            return [
                'success' => false,
                'error' => 'Promotion requirements not met',
                'details' => $check,
            ];
        }
        
        $fromTier = $check['current_tier'];
        $toTier = $check['next_tier'];
        $tierData = $this->supabase->select('edu_user_tier', 'student_id=eq.' . $studentId, 1);
        $xp = (int)($tierData[0]['xp_current'] ?? 0);
        
        $this->supabase->update('edu_user_tier', 'student_id=eq.' . $studentId, [
            'tier_id' => $toTier,
            'updated_at' => date('c'),
        ]);
        
        $this->supabase->insert('edu_tier_history', [
            'student_id' => $studentId,
            'from_tier' => $fromTier,
            'to_tier' => $toTier,
            'trigger_event' => 'manual_promotion',
            'xp_at_promotion' => $xp,
        ]);
        
        return [
            'success' => true,
            'from_tier' => $fromTier,
            'to_tier' => $toTier,
            'tier_label' => self::TIER_LABELS[$toTier] ?? ['en' => $toTier, 'ko' => $toTier],
        ];
    }

    public function getProgressSummary(string $studentId): array
    {
        $tier = $this->supabase->select('edu_user_tier', 'student_id=eq.' . $studentId, 1);
        $tierData = $tier[0] ?? ['tier_id' => 'bronze', 'xp_current' => 0, 'streak_days' => 0];
        
        $check = $this->checkPromotion($studentId);
        
        return [
            'tier' => [
                'id' => $tierData['tier_id'],
                'label' => self::TIER_LABELS[$tierData['tier_id']] ?? ['en' => 'Bronze', 'ko' => '브론즈'],
            ],
            'xp' => (int)$tierData['xp_current'],
            'streak' => (int)$tierData['streak_days'],
            'promotion' => $check,
        ];
    }

    private function countCompletedQuests(string $studentId): int
    {
        $sessions = $this->supabase->select(
            'edu_quest_sessions',
            'student_id=eq.' . $studentId . '&stage=eq.completed',
            1000
        );
        return count($sessions);
    }

    private function countStanceChanges(string $studentId): int
    {
        $cards = $this->supabase->select(
            'edu_share_cards',
            'student_id=eq.' . $studentId . '&stance_changed=eq.true',
            1000
        );
        return count($cards);
    }
}

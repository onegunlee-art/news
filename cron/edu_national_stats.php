<?php
/**
 * GIST EDU — National Stats Refresh
 * 5분 주기 실행 (crontab: */5 * * * *)
 * 
 * 전국 찬반 % 및 입장 변경률 갱신
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/agents/autoload.php';
require_once $projectRoot . '/public/api/edu/lib/bootstrap.php';

function statsLog(string $msg, $data = null): void {
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($logDir . '/edu_national_stats.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

statsLog('National stats refresh started');

$supabase = eduSupabase();
if (!$supabase->isConfigured()) {
    statsLog('ERROR: Supabase not configured');
    exit(1);
}

$quests = $supabase->select(
    'edu_daily_quests',
    'status=eq.approved&live_at=not.is.null&order=live_at.desc',
    20
);

statsLog('Processing quests', ['count' => count($quests)]);

foreach ($quests as $quest) {
    $questId = $quest['id'];
    $questCode = $quest['quest_code'];
    
    $sessions = $supabase->select(
        'edu_quest_sessions',
        'quest_id=eq.' . $questId . '&stage=eq.completed',
        10000
    );
    
    $totalParticipants = count($sessions);
    
    if ($totalParticipants === 0) {
        continue;
    }
    
    $proCount = 0;
    $conCount = 0;
    $stanceChangedCount = 0;
    $confidenceBefore = [];
    $confidenceAfter = [];
    
    foreach ($sessions as $session) {
        $sessionId = $session['id'];
        
        $hypothesis = $supabase->select(
            'edu_hypothesis_versions',
            'session_id=eq.' . $sessionId . '&order=version.asc',
            2
        );
        
        $v1 = $hypothesis[0] ?? null;
        $v2 = $hypothesis[1] ?? $v1;
        
        if ($v1) {
            $confidenceBefore[] = (int)($v1['confidence_level'] ?? 3);
        }
        if ($v2) {
            $confidenceAfter[] = (int)($v2['confidence_level'] ?? 3);
            
            if (($v2['stance'] ?? '') === 'pro') {
                $proCount++;
            } else {
                $conCount++;
            }
            
            if ($v1 && $v1['stance'] !== $v2['stance']) {
                $stanceChangedCount++;
            }
        }
    }
    
    $proPct = $totalParticipants > 0 ? round(($proCount / $totalParticipants) * 100, 2) : 50.00;
    $conPct = $totalParticipants > 0 ? round(($conCount / $totalParticipants) * 100, 2) : 50.00;
    $stanceChangedPct = $totalParticipants > 0 ? round(($stanceChangedCount / $totalParticipants) * 100, 2) : 0.00;
    
    $avgConfidenceBefore = count($confidenceBefore) > 0 
        ? round(array_sum($confidenceBefore) / count($confidenceBefore), 2) 
        : null;
    $avgConfidenceAfter = count($confidenceAfter) > 0 
        ? round(array_sum($confidenceAfter) / count($confidenceAfter), 2) 
        : null;
    
    $existing = $supabase->select('edu_national_stats', 'quest_id=eq.' . $questId, 1);
    
    $statsData = [
        'quest_id' => $questId,
        'pro_pct' => $proPct,
        'con_pct' => $conPct,
        'stance_changed_pct' => $stanceChangedPct,
        'avg_confidence_before' => $avgConfidenceBefore,
        'avg_confidence_after' => $avgConfidenceAfter,
        'total_participants' => $totalParticipants,
        'updated_at' => date('c'),
    ];
    
    if (!empty($existing[0])) {
        $supabase->update('edu_national_stats', 'quest_id=eq.' . $questId, $statsData);
    } else {
        $supabase->insert('edu_national_stats', $statsData);
    }
    
    statsLog('Updated stats', [
        'quest_code' => $questCode,
        'participants' => $totalParticipants,
        'pro_pct' => $proPct,
        'changed_pct' => $stanceChangedPct,
    ]);
}

statsLog('National stats refresh completed');

<?php
/**
 * GIST EDU — draft storage schema probe + resilient save
 */
declare(strict_types=1);

/** @return array{drafts_full_text: bool, drafts_essay_structure: bool, drafts_student_edited: bool, sessions_blueprint_json: bool, sessions_dialogue_json: bool, draft_storage: string} */
function eduProbeDraftStorageSchema(\Agents\Services\SupabaseService $supabase): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $draftsProbe = $supabase->select(
        'edu_writing_drafts',
        'select=full_text,essay_structure,student_edited',
        1
    );
    $draftsOk = $draftsProbe !== null;

    $sessionsProbe = $supabase->select(
        'edu_quest_sessions',
        'select=blueprint_json,dialogue_json',
        1
    );
    $sessionsOk = $sessionsProbe !== null;

    $draftStorage = ($draftsOk && $sessionsOk) ? 'drafts_primary' : 'blueprint_fallback_only';

    $cached = [
        'drafts_full_text' => $draftsOk,
        'drafts_essay_structure' => $draftsOk,
        'drafts_student_edited' => $draftsOk,
        'sessions_blueprint_json' => $sessionsOk,
        'sessions_dialogue_json' => $sessionsOk,
        'draft_storage' => $draftStorage,
    ];

    return $cached;
}

function eduStrictDraftStorage(): bool
{
    $v = getenv('EDU_STRICT_DRAFT_STORAGE');
    if ($v === false || $v === '') {
        return true;
    }

    return !in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed>|null $existing
 * @return array{ok: bool, used_fallback: bool, error: string}
 */
function eduSaveWritingDraft(
    \Agents\Services\SupabaseService $supabase,
    string $sessionId,
    string $studentId,
    array $payload,
    ?array $existing,
    string $context = 'compose'
): array {
    $schema = eduProbeDraftStorageSchema($supabase);
    $structuredKeys = ['full_text', 'essay_structure', 'student_edited'];
    $hasStructured = $schema['drafts_full_text'] && $schema['drafts_essay_structure'];

    if (!$hasStructured) {
        error_log(sprintf(
            '[EDU draft:%s] structured columns missing session=%s keys=%s supabase_error=%s',
            $context,
            $sessionId,
            implode(',', array_keys($payload)),
            $supabase->getLastError()
        ));
    }

    if (!empty($existing['id'])) {
        $saved = $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, $payload);
        if ($saved !== null) {
            return ['ok' => true, 'used_fallback' => false, 'error' => ''];
        }

        if ($hasStructured && eduStrictDraftStorage()) {
            $err = $supabase->getLastError();
            error_log(sprintf(
                '[EDU draft:%s] strict save failed session=%s error=%s',
                $context,
                $sessionId,
                $err
            ));

            return ['ok' => false, 'used_fallback' => false, 'error' => $err];
        }

        $fallback = $payload;
        foreach ($structuredKeys as $key) {
            unset($fallback[$key]);
        }
        error_log(sprintf(
            '[EDU draft:%s] falling back without structured columns session=%s dropped=%s error=%s',
            $context,
            $sessionId,
            implode(',', $structuredKeys),
            $supabase->getLastError()
        ));
        $retry = $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, $fallback);

        return [
            'ok' => $retry !== null,
            'used_fallback' => true,
            'error' => $retry === null ? $supabase->getLastError() : '',
        ];
    }

    $insertPayload = array_merge($payload, [
        'session_id' => $sessionId,
        'student_id' => $studentId,
    ]);
    if (!isset($insertPayload['stance_delta'])) {
        $insertPayload['stance_delta'] = 'unchanged';
    }

    $inserted = $supabase->insert('edu_writing_drafts', $insertPayload);
    if ($inserted !== null) {
        return ['ok' => true, 'used_fallback' => false, 'error' => ''];
    }

    if ($hasStructured && eduStrictDraftStorage()) {
        $err = $supabase->getLastError();
        error_log(sprintf(
            '[EDU draft:%s] strict insert failed session=%s error=%s',
            $context,
            $sessionId,
            $err
        ));

        return ['ok' => false, 'used_fallback' => false, 'error' => $err];
    }

    $fallback = $insertPayload;
    foreach ($structuredKeys as $key) {
        unset($fallback[$key]);
    }
    error_log(sprintf(
        '[EDU draft:%s] falling back insert without structured columns session=%s dropped=%s error=%s',
        $context,
        $sessionId,
        implode(',', $structuredKeys),
        $supabase->getLastError()
    ));
    $retry = $supabase->insert('edu_writing_drafts', $fallback);

    return [
        'ok' => $retry !== null,
        'used_fallback' => true,
        'error' => $retry === null ? $supabase->getLastError() : '',
    ];
}

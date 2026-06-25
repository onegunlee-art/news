const EDU_TOKEN_KEY = 'edu_access_token'
const EDU_DISPLAY_NAME_KEY = 'edu_display_name'
const EDU_STUDENT_KEY = 'edu_student'

export interface EduStudent {
  id: string
  display_name: string
  grade_band?: string
  profile_image?: string | null
  email?: string | null
  has_kakao?: boolean
}

export function getEduToken(): string | null {
  return localStorage.getItem(EDU_TOKEN_KEY)
}

export function setEduToken(token: string): void {
  localStorage.setItem(EDU_TOKEN_KEY, token)
}

export function clearEduToken(): void {
  localStorage.removeItem(EDU_TOKEN_KEY)
  localStorage.removeItem(EDU_DISPLAY_NAME_KEY)
  localStorage.removeItem(EDU_STUDENT_KEY)
  localStorage.removeItem('edu_refresh_token')
}

export function getEduDisplayName(): string | null {
  return localStorage.getItem(EDU_DISPLAY_NAME_KEY)
}

export function setEduDisplayName(name: string): void {
  localStorage.setItem(EDU_DISPLAY_NAME_KEY, name)
}

export function getEduStudent(): EduStudent | null {
  const raw = localStorage.getItem(EDU_STUDENT_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as EduStudent
  } catch {
    return null
  }
}

export function setEduStudent(student: EduStudent): void {
  localStorage.setItem(EDU_STUDENT_KEY, JSON.stringify(student))
  if (student.display_name) {
    setEduDisplayName(student.display_name)
  }
}

export function getEduKakaoLoginUrl(): string {
  return '/api/edu/auth_kakao.php'
}

export interface EduTierProgress {
  tier_id: string
  tier_label_en: string
  tier_label_ko: string
  status: string
  next_tier_id: string | null
  next_tier_label_en: string | null
  xp_current: number
  xp_next_tier: number | null
  progress_pct: number
  streak_days: number
  show_quest_cta: boolean
}

export interface EduQuestArticle {
  news_id: number
  role: string
  title: string
  gist_url: string
  excerpt?: string
  why_important?: string
  source_outlet?: string
  media_perspective?: string
  published_at?: string | null
  image_url?: string | null
}


export interface EduDialogueTurn {
  role: 'student' | 'assistant'
  content: string
  agent?: string | null
  at?: string
}

export interface EduStructureSection {
  heading: string
  role?: string
  bullets?: string[]
}

export interface EduStructurePreview {
  title?: string
  subtitle?: string
  sections?: EduStructureSection[]
  conclusion_heading?: string
  conclusion_bullets?: string[]
  student_stance?: string
}

export interface EduBlueprint {
  stance?: 'pro' | 'con' | null
  reason?: string
  evidence?: string
  evidence_nudge_count?: number
  phase?: string
  progress_pct?: number
  ready_for_compose?: boolean
  reflection_lines?: string[]
  stance_changed?: boolean
  essay_structure?: EduStructurePreview
  /** axis_guide 코치 — UI 탐구 체크용 (표시만, 로직 무관) */
  guide_axis_index?: number
  guide_axis_answers?: Record<string, string>
  /** 버튼 선택 후 "왜?" 서술 대기 (2-C) */
  guide_axis_pending_why?: { axis_id: string; choice: string } | null
}

export interface EduChatResponse {
  success: boolean
  session_id: string
  stage: string
  phase?: string
  assistant_message?: string
  progress_pct?: number
  should_compose?: boolean
  articles?: EduQuestArticle[]
  counter_argument?: string
  mixup_sources?: Array<{ source: string; excerpt: string }>
  summary_lines?: string[]
  stance_changed?: boolean
  blueprint?: EduBlueprint
  needs_followup?: boolean
  feedback_hint?: string | null
  structure_preview?: EduStructurePreview
  /** axis_guide 선택형 — 2-A 백엔드, 카드 버튼 UI용 */
  choice_question?: boolean
  options?: string[]
  /** 선택형일 때 질문 본문(선택지 괄호 제거) */
  choice_question_text?: string
  /** 서술형 카드 — 입력 위 한 줄 고정 라벨 */
  narrative_prompt?: string
}

export interface EduEssaySection {
  heading: string
  paragraphs: string[]
}

export interface EduComposeResponse {
  success: boolean
  session_id: string
  saved?: boolean
  saved_at?: string
  stage: string
  title?: string
  subtitle?: string
  sections?: EduEssaySection[]
  conclusion_heading?: string
  conclusion_paragraphs?: string[]
  essay_structure?: Record<string, unknown>
  full_text?: string
  scqa_parts?: Record<string, string>
  hero_sentence?: string | null
  quality_score?: number
  structure_score?: number
  feedback?: string
  xp_gained?: number
  tier?: EduTierProgress
  progress_pct?: number
  already_completed?: boolean
}

export interface EduSessionState {
  success: boolean
  session_id: string
  stage: string
  quest: EduQuest
  blueprint: EduBlueprint
  dialogue: EduDialogueTurn[]
  progress_pct: number
  essay: {
    title?: string | null
    subtitle?: string | null
    sections?: EduEssaySection[]
    conclusion_heading?: string
    conclusion_paragraphs?: string[]
    full_text: string
    hero_sentence: string | null
    feedback: string | null
    quality_score: number
    scqa_parts: Record<string, string> | null
    stance_changed: boolean
  } | null
  choice_question?: boolean
  options?: string[]
  choice_question_text?: string
}

export interface EduCompletedSession {
  session_id: string
  quest_id: string
  quest_code: string
  quest_title: string
  time_anchor?: string | null
  stance?: 'pro' | 'con' | null
  stage: string
  started_at?: string | null
  completed_at?: string | null
  essay_title?: string | null
  hero_sentence?: string | null
  image_url?: string | null
}

export interface EduQuest {
  quest_id: string
  quest_code: string
  quest_title: string
  pro_line: string
  con_line: string
  alignment_summary: string
  conflict_summary: string
  time_anchor?: string | null
  quest_frame?: string | null
  entry_mode?: 'open_response' | 'stance_pick' | null
  hook_short?: string | null
  hook_full?: string | null
  cover_image_url?: string | null
  articles: EduQuestArticle[]
}

export interface EduQuestListItem {
  quest_id: string
  quest_code: string
  quest_title: string
  pro_line: string
  con_line: string
  conflict_summary: string
  grade_band: string
  time_anchor?: string | null
  quest_frame?: string | null
  category?: string | null
  category_label?: string | null
  shelf?: string | null
  shelf_label?: string | null
  lens?: string | null
  lens_label?: string | null
  subtitle?: string | null
  hook_short?: string | null
  cover_image_url?: string | null
  is_live: boolean
  live_at?: string | null
  completed: boolean
}

export interface EduExploreShelf {
  shelf_id: string
  label: string
  count: number
  categories: string[]
}

async function eduFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getEduToken()
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> | undefined),
  }
  if (token) {
    headers['X-Edu-Token'] = token
  }

  const res = await fetch(path, { ...options, headers })
  const raw = await res.text()
  let data: { success?: boolean; error?: string }
  try {
    data = raw ? (JSON.parse(raw) as { success?: boolean; error?: string }) : {}
  } catch {
    const hint = raw.trimStart().startsWith('<') ? '서버가 HTML 오류 페이지를 반환했어요' : '응답 형식 오류'
    throw new Error(`${hint} (${res.status}). 잠시 후 다시 시도해 주세요.`)
  }
  if (!res.ok || data.success === false) {
    throw new Error(data.error || `Request failed (${res.status})`)
  }
  return data as T
}

export const eduApi = {
  redeemInvite: (invite_code: string) =>
    eduFetch<{ token: string; student: { id: string; display_name: string; grade_band: string } }>(
      '/api/edu/invite/redeem.php',
      { method: 'POST', body: JSON.stringify({ invite_code }) }
    ),

  createGuestSession: () =>
    eduFetch<{ token: string; student: { id: string; display_name: string; grade_band: string } }>(
      '/api/edu/guest/start.php',
      { method: 'POST' }
    ),

  todayQuest: () =>
    eduFetch<{
      quest: EduQuest | null
      active_session: { session_id: string; stage: string; stance?: string } | null
      tier?: EduTierProgress
      ui_steps?: string[]
      participation?: { total: number; display: string }
      curiosity_locked?: boolean
      existing_session?: { session_id: string; stage: string; stance?: string } | null
    }>('/api/edu/quests/today.php'),

  listQuests: (params?: { limit?: number; frame?: string; category?: string; shelf?: string }) => {
    const q = new URLSearchParams()
    if (params?.limit) q.set('limit', String(params.limit))
    if (params?.frame) q.set('frame', params.frame)
    if (params?.category) q.set('category', params.category)
    if (params?.shelf) q.set('shelf', params.shelf)
    const qs = q.toString()
    return eduFetch<{ quests: EduQuestListItem[]; count: number }>(
      `/api/edu/quests/list.php${qs ? `?${qs}` : ''}`
    )
  },

  exploreCategories: (frame = 'all') =>
    eduFetch<{ total: number; shelves: EduExploreShelf[] }>(
      `/api/edu/quests/categories.php?frame=${encodeURIComponent(frame)}`
    ),

  startSession: (quest_id?: string) =>
    eduFetch<{ session_id: string; stage: string; resumed: boolean }>(
      '/api/edu/session/start.php',
      { method: 'POST', body: JSON.stringify(quest_id ? { quest_id } : {}) }
    ),

  setStance: (session_id: string, stance: 'pro' | 'con') =>
    eduFetch<{
      session_id: string
      stage: string
      hammer: {
        counter_line: string
        hammer_hint: string
        conflict_summary: string
        reflection_question: string
      }
    }>('/api/edu/session/stance.php', {
      method: 'POST',
      body: JSON.stringify({ session_id, stance }),
    }),

  advanceHammer: (session_id: string, reflection_note?: string) =>
    eduFetch<{ session_id: string; stage: string; writing_prompt: string }>(
      '/api/edu/session/hammer.php',
      { method: 'POST', body: JSON.stringify({ session_id, reflection_note }) }
    ),

  submitWriting: (session_id: string, v1_sentences: string[], v2_sentences?: string[]) =>
    eduFetch<{
      session_id: string
      stage: string
      teacher_feedback: string | null
      needs_v2: boolean
      hero_sentence: string | null
    }>('/api/edu/session/writing.php', {
      method: 'POST',
      body: JSON.stringify({ session_id, v1_sentences, v2_sentences }),
    }),

  complete: (session_id: string, v2_sentences: string[], stance_delta?: string) =>
    eduFetch<{
      session_id: string
      hero_sentence: string | null
      xp_gained: number
      tier: EduTierProgress
      share_card: { kicker: string; after: string }
    }>('/api/edu/session/complete.php', {
      method: 'POST',
      body: JSON.stringify({ session_id, v2_sentences, stance_delta }),
    }),

  tierProgress: () =>
    eduFetch<{ tier: EduTierProgress }>('/api/edu/tier/progress.php'),

  studentProfile: () =>
    eduFetch<{
      student: EduStudent
      tier: EduTierProgress
      completed_count: number
    }>('/api/edu/student/profile.php'),

  studentSessions: (status: 'completed' | 'in_progress' | 'all' = 'completed') =>
    eduFetch<{ sessions: EduCompletedSession[] }>(
      `/api/edu/student/sessions.php?status=${status}`
    ),

  getNationalStats: (questId: string) =>
    eduFetch<{
      stats: { pro_pct: number; con_pct: number; stance_changed_pct: number }
      quest: { quest_id: string; quest_code: string; quest_title: string; pro_line: string; con_line: string }
      student_stance: 'pro' | 'con' | null
    }>(`/api/edu/stats/national.php?quest_id=${questId}`),

  getShareCard: (sessionId: string) =>
    eduFetch<{
      card: {
        quest_code: string
        quest_title: string
        initial_stance: 'pro' | 'con'
        final_stance: 'pro' | 'con'
        stance_changed: boolean
        streak_days: number
        tier_name: string
        hero_sentence: string
        national_changed_pct: number | null
      }
      share_url: string
    }>(`/api/edu/share_card.php?session_id=${sessionId}`),

  createShareCard: (sessionId: string) =>
    eduFetch<{
      card: {
        quest_code: string
        quest_title: string
        initial_stance: 'pro' | 'con'
        final_stance: 'pro' | 'con'
        stance_changed: boolean
        streak_days: number
        tier_name: string
        hero_sentence: string
        national_changed_pct: number | null
      }
      share_url: string
    }>('/api/edu/share_card.php', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId }),
    }),

  getShareCardByHash: (hash: string) =>
    fetch(`/api/edu/share_card.php?hash=${hash}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success) throw new Error(data.error || 'Not found')
        return data as {
          card: {
            quest_code: string
            quest_title: string
            initial_stance: 'pro' | 'con'
            final_stance: 'pro' | 'con'
            stance_changed: boolean
            streak_days: number
            tier_name: string
            hero_sentence: string
            national_changed_pct: number | null
          }
        }
      }),

  getSessionState: (sessionId: string) =>
    eduFetch<EduSessionState>(`/api/edu/session/state.php?session_id=${encodeURIComponent(sessionId)}`),

  sendChat: (
    sessionId: string,
    payload: { message?: string; action?: string; stance?: 'pro' | 'con'; stance_changed?: boolean; new_stance?: string }
  ) =>
    eduFetch<EduChatResponse>('/api/edu/session/chat.php', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId, ...payload }),
    }),

  composeEssay: (sessionId: string, force = false) =>
    eduFetch<EduComposeResponse>('/api/edu/session/compose.php', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId, force }),
    }),

  saveEssay: (
    sessionId: string,
    essay: {
      title?: string | null
      subtitle?: string | null
      sections?: EduEssaySection[]
      conclusion_heading?: string
      conclusion_paragraphs?: string[]
      hero_sentence?: string | null
      full_text?: string
    }
  ) =>
    eduFetch<{
      success: boolean
      session_id: string
      saved: boolean
      saved_at: string
      title?: string
      subtitle?: string
      sections?: EduEssaySection[]
      conclusion_heading?: string
      conclusion_paragraphs?: string[]
      full_text?: string
      hero_sentence?: string | null
    }>('/api/edu/session/save_essay.php', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId, ...essay }),
    }),
}

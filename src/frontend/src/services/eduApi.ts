const EDU_TOKEN_KEY = 'edu_access_token'

export function getEduToken(): string | null {
  return localStorage.getItem(EDU_TOKEN_KEY)
}

export function setEduToken(token: string): void {
  localStorage.setItem(EDU_TOKEN_KEY, token)
}

export function clearEduToken(): void {
  localStorage.removeItem(EDU_TOKEN_KEY)
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
}

export interface EduQuest {
  quest_id: string
  quest_code: string
  quest_title: string
  pro_line: string
  con_line: string
  alignment_summary: string
  conflict_summary: string
  articles: EduQuestArticle[]
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
  const data = await res.json()
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

  todayQuest: () =>
    eduFetch<{
      quest: EduQuest
      active_session: { session_id: string; stage: string; stance?: string } | null
      tier: EduTierProgress
      ui_steps: string[]
    }>('/api/edu/quests/today.php'),

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
}

/** EDU 코치 UI — 카드형 vs 채팅형 (채팅 버전 보존용) */
export type EduCoachUiMode = 'cards' | 'chat'

const STORAGE_KEY = 'edu_coach_ui'

export function resolveEduCoachUiMode(searchParams: URLSearchParams): EduCoachUiMode {
  const fromQuery = searchParams.get('ui')
  if (fromQuery === 'chat' || fromQuery === 'cards') {
    localStorage.setItem(STORAGE_KEY, fromQuery)
    return fromQuery
  }
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'chat' || stored === 'cards') {
    return stored
  }
  return 'cards'
}

export function setEduCoachUiMode(mode: EduCoachUiMode): void {
  localStorage.setItem(STORAGE_KEY, mode)
}

export function eduQuestPathWithUi(questId?: string | null, mode: EduCoachUiMode = 'cards'): string {
  const params = new URLSearchParams()
  if (questId) params.set('quest_id', questId)
  params.set('ui', mode)
  const qs = params.toString()
  return qs ? `/edu/quest?${qs}` : '/edu/quest'
}

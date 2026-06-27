/** L1~L5 코치 깊이 — 유일 메인 레벨 (관찰자~칼럼니스트) */
export interface EduCoachLevelInfo {
  coach_level: number
  label_ko: string
  label_en: string
  role_id: string
}

export const EDU_COACH_LEVELS: EduCoachLevelInfo[] = [
  { coach_level: 1, label_ko: '관찰자', label_en: 'Observer', role_id: 'observer' },
  { coach_level: 2, label_ko: '질문자', label_en: 'Questioner', role_id: 'questioner' },
  { coach_level: 3, label_ko: '논객', label_en: 'Debater', role_id: 'debater' },
  { coach_level: 4, label_ko: '분석가', label_en: 'Analyst', role_id: 'analyst' },
  { coach_level: 5, label_ko: '칼럼니스트', label_en: 'Columnist', role_id: 'columnist' },
]

export const EDU_COACH_LEVEL_MEDAL: Record<number, { bg: string; ring: string; icon: string }> = {
  1: { bg: '#e8e8e8', ring: '#999999', icon: '👁' },
  2: { bg: '#8b9aab', ring: '#5c6b7a', icon: '❓' },
  3: { bg: '#5a9a8a', ring: '#3d7268', icon: '⚖' },
  4: { bg: '#7b6b9e', ring: '#5a4d78', icon: '🔍' },
  5: { bg: '#f05123', ring: '#d9451c', icon: '✦' },
}

export function eduCoachLevelByNumber(level: number): EduCoachLevelInfo {
  return EDU_COACH_LEVELS.find((l) => l.coach_level === level) ?? EDU_COACH_LEVELS[0]
}

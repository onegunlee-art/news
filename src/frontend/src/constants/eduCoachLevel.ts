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

export const EDU_COACH_LEVEL_MEDAL: Record<number, { bg: string; ring: string }> = {
  1: { bg: '#f5f5f5', ring: '#cccccc' },
  2: { bg: '#f0f0f0', ring: '#bbbbbb' },
  3: { bg: '#f0f0f0', ring: '#bbbbbb' },
  4: { bg: '#f0f0f0', ring: '#bbbbbb' },
  5: { bg: '#D85A30', ring: '#B84A26' },
}

export function eduCoachLevelByNumber(level: number): EduCoachLevelInfo {
  return EDU_COACH_LEVELS.find((l) => l.coach_level === level) ?? EDU_COACH_LEVELS[0]
}

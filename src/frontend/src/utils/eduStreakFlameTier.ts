/** 스트릭 일수 → 불꽃 크기 티어 (로직은 streak_days 값만 사용) */
export type EduStreakFlameTier = 'sm' | 'md' | 'lg'

export function eduStreakFlameTier(streakDays: number): EduStreakFlameTier {
  if (streakDays >= 7) return 'lg'
  if (streakDays >= 3) return 'md'
  return 'sm'
}

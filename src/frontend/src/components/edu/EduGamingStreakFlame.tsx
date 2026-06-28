import { useId } from 'react'
import { eduGame } from '../../constants/eduGameTheme'
import { eduStreakFlameTier, type EduStreakFlameTier } from '../../utils/eduStreakFlameTier'

type Props = {
  streakDays: number
  /** compact=상단바, hero=완주·프로필 */
  variant?: 'compact' | 'hero'
  showCount?: boolean
  animate?: boolean
  className?: string
}

const TIER_PX: Record<EduStreakFlameTier, { compact: number; hero: number }> = {
  sm: { compact: 22, hero: 52 },
  md: { compact: 28, hero: 68 },
  lg: { compact: 34, hero: 84 },
}

/** 듀얼톤 게이밍 불꽃 + 연속일 숫자 (스트릭 값만 반영, 게이지 무관) */
export default function EduGamingStreakFlame({
  streakDays,
  variant = 'compact',
  showCount = true,
  animate = true,
  className = '',
}: Props) {
  const gradId = useId()
  const tier = eduStreakFlameTier(streakDays)
  const px = TIER_PX[tier][variant]
  const countSize = variant === 'hero' ? '2.25rem' : '0.875rem'
  const animClass = animate ? 'edu-game-streak-live' : ''

  return (
    <div
      className={`inline-flex items-center gap-1 shrink-0 ${className}`}
      aria-label={`연속 탐구 ${streakDays}일`}
    >
      <svg
        width={px}
        height={px}
        viewBox="0 0 48 56"
        className={animClass}
        aria-hidden
      >
        <defs>
          <linearGradient id={gradId} x1="50%" y1="100%" x2="50%" y2="0%">
            <stop offset="0%" stopColor={eduGame.primaryDark} />
            <stop offset="55%" stopColor={eduGame.primary} />
            <stop offset="100%" stopColor="#FF8C42" />
          </linearGradient>
        </defs>
        {/* 겉불꽃 */}
        <path
          d="M24 4c-2 8-10 12-10 22a10 10 0 0 0 20 0c0-6-6-10-8-14 2 4 6 6 8 10 2-6-2-12-10-18z"
          fill={`url(#${gradId})`}
        />
        {/* 속불꽃 — 3일+ */}
        {(tier === 'md' || tier === 'lg') && (
          <path
            d="M24 22c-3 4-6 7-6 12a6 6 0 0 0 12 0c0-3-3-6-6-12z"
            fill="#FFD54F"
            opacity={0.95}
          />
        )}
        {/* 7일+ 번개 */}
        {tier === 'lg' && (
          <path
            d="M28 8l-3 8h4l-2 10 6-12h-4l3-6z"
            fill="#FFEB3B"
            stroke="#FFF59D"
            strokeWidth="0.5"
          />
        )}
      </svg>
      {showCount && (
        <span
          className="font-bold tabular-nums leading-none"
          style={{
            fontSize: countSize,
            color: eduGame.primary,
          }}
        >
          {streakDays}
        </span>
      )}
    </div>
  )
}

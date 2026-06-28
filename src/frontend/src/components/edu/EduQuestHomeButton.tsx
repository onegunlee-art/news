import { Link } from 'react-router-dom'
import { eduGame } from '../../constants/eduGameTheme'

type Props = {
  to?: string
  className?: string
}

/** 카드 탐구 — 미니멀 홈 버튼 (아이콘만) */
export default function EduQuestHomeButton({ to = '/edu', className = '' }: Props) {
  return (
    <Link
      to={to}
      className={`inline-flex items-center justify-center w-9 h-9 rounded-xl border-2 shrink-0 touch-manipulation transition-transform active:scale-95 ${className}`}
      style={{
        borderColor: eduGame.border,
        backgroundColor: eduGame.bg,
        color: eduGame.ink,
      }}
      aria-label="홈으로"
      title="홈으로"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
        <path
          d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5H15v-5.5h-2V20.5H5.5A1.5 1.5 0 0 1 4 19v-8.5z"
          stroke="currentColor"
          strokeWidth="1.75"
          strokeLinejoin="round"
        />
      </svg>
    </Link>
  )
}

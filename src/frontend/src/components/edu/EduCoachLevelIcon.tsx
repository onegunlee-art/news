import { eduGame } from '../../constants/eduGameTheme'

type Props = {
  level: number
  size?: number
  className?: string
}

/** L1~L5 코치 레벨 미니멀 라인 아이콘 (표시만) */
export default function EduCoachLevelIcon({ level, size = 24, className = '' }: Props) {
  const lv = Math.min(5, Math.max(1, level))
  const isGrad = lv === 5
  const stroke = isGrad ? '#ffffff' : eduGame.ink
  const common = {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke,
    strokeWidth: 1.75,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    className,
    'aria-hidden': true,
  }

  switch (lv) {
    case 1:
      return (
        <svg {...common}>
          <path d="M12 5c-3.5 0-6 2.2-6 5.5 0 2.2 1.4 4 3.5 4.8V19h5v-3.7c2.1-.8 3.5-2.6 3.5-4.8C18 7.2 15.5 5 12 5z" />
          <circle cx="12" cy="10.5" r="2" fill={stroke} stroke="none" />
        </svg>
      )
    case 2:
      return (
        <svg {...common}>
          <path d="M9.5 8a2.5 2.5 0 1 1 4.2 1.8c-.9.8-1.7 1.4-1.7 2.7V16" />
          <circle cx="12" cy="19" r="1.25" fill={stroke} stroke="none" />
        </svg>
      )
    case 3:
      return (
        <svg {...common}>
          <path d="M4 10c0-2 1.5-3.5 3.5-3.5H9l3-1.8L15 6.5h1.5C18.5 6.5 20 8 20 10v4c0 2-1.5 3.5-3.5 3.5h-9C5.5 17.5 4 16 4 14v-4z" />
          <path d="M14 8.5c1.5-1 3.2-.6 4 1 .8 1.6-.2 3.4-1.8 4" transform="translate(1,1) scale(0.7)" />
        </svg>
      )
    case 4:
      return (
        <svg {...common}>
          <circle cx="10.5" cy="10.5" r="5.5" />
          <path d="M15 15l4.5 4.5" />
        </svg>
      )
    default:
      return (
        <svg {...common}>
          <path
            d="M12 3L7.5 18.5h9L12 3z"
            fill={stroke}
            stroke="none"
          />
          <path d="M12 15v6" />
          <path d="M9.5 21h5" />
        </svg>
      )
  }
}

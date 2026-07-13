import { Link } from 'react-router-dom'
import { eduGame } from '../../constants/eduGameTheme'

type Props = {
  size?: 'sm' | 'md' | 'lg'
  variant?: 'light' | 'dark'
  to?: string
  className?: string
}

/** ● gistudy — the gist + study 워드플레이 */
export default function EduGistudyLogo({
  size = 'md',
  variant = 'light',
  to = '/edu',
  className = '',
}: Props) {
  const scale = size === 'sm' ? 1 : size === 'lg' ? 1.35 : 1.15
  const dotSize = Math.round(10 * scale)
  const fontSize = size === 'sm' ? '1.125rem' : size === 'lg' ? '1.625rem' : '1.375rem'
  const textColor = variant === 'dark' ? '#ffffff' : eduGame.ink

  const inner = (
    <span
      className={`inline-flex items-center gap-1.5 shrink-0 ${className}`}
      aria-label="gistudy"
    >
      <span
        className="rounded-full shrink-0"
        style={{
          width: dotSize,
          height: dotSize,
          backgroundColor: eduGame.primary,
        }}
        aria-hidden
      />
      <span
        className="font-semibold tracking-tight leading-none"
        style={{
          fontFamily: eduGame.fontLogo,
          fontSize,
          color: textColor,
        }}
      >
        gistudy
      </span>
    </span>
  )

  if (to) {
    return (
      <Link to={to} className="inline-flex no-underline hover:opacity-90 transition-opacity">
        {inner}
      </Link>
    )
  }

  return inner
}

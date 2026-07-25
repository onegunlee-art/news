import { clsx } from 'clsx'

export type DiscoveryIconName =
  | 'search'
  | 'theme-toggle'
  | 'clock'
  | 'x'
  | 'bookmark'
  | 'calendar'
  | 'tune'
  | 'sparkles'
  | 'circle'
  | 'triangle'
  | 'square'
  | 'chevron-right'
  | 'arrow-back'
  | 'compass'
  | 'user'
  | 'info'
  | 'check'
  | 'forum'

interface Props {
  name: DiscoveryIconName
  size?: number
  className?: string
  stroke?: string
}

const STROKE = 'currentColor'

function IconPath({ name }: { name: DiscoveryIconName }) {
  switch (name) {
    case 'search':
      return (
        <>
          <circle cx="11" cy="11" r="7" strokeWidth="1.75" fill="none" />
          <path d="M16 16l4 4" strokeWidth="1.75" strokeLinecap="round" />
        </>
      )
    case 'theme-toggle':
      return (
        <>
          <circle cx="12" cy="12" r="4.5" strokeWidth="1.75" fill="none" />
          <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" strokeWidth="1.5" strokeLinecap="round" />
        </>
      )
    case 'clock':
      return (
        <>
          <circle cx="12" cy="12" r="9" strokeWidth="1.75" fill="none" />
          <path d="M12 7v5l3 2" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
        </>
      )
    case 'x':
      return <path d="M7 7l10 10M17 7L7 17" strokeWidth="1.75" strokeLinecap="round" />
    case 'bookmark':
      return <path d="M6 4h12v16l-6-4-6 4V4z" strokeWidth="1.75" strokeLinejoin="round" fill="none" />
    case 'calendar':
      return (
        <>
          <rect x="3" y="5" width="18" height="16" rx="2" strokeWidth="1.75" fill="none" />
          <path d="M3 9h18M8 3v4M16 3v4" strokeWidth="1.75" strokeLinecap="round" />
        </>
      )
    case 'tune':
      return (
        <>
          <path d="M4 6h16M7 12h10M10 18h4" strokeWidth="1.75" strokeLinecap="round" />
          <circle cx="6" cy="6" r="2" fill={STROKE} stroke="none" />
          <circle cx="14" cy="12" r="2" fill={STROKE} stroke="none" />
          <circle cx="12" cy="18" r="2" fill={STROKE} stroke="none" />
        </>
      )
    case 'sparkles':
      return (
        <>
          <path d="M12 3l1 4 4 1-4 1-1 4-1-4-4-1 4-1 1-4z" strokeWidth="1.5" strokeLinejoin="round" fill="none" />
          <path d="M19 8l.5 1.5L21 10l-1.5.5L19 12l-.5-1.5L17 10l1.5-.5L19 8z" fill={STROKE} stroke="none" />
        </>
      )
    case 'circle':
      return <circle cx="12" cy="12" r="8" strokeWidth="1.75" fill="none" />
    case 'triangle':
      return <path d="M12 4l8 14H4L12 4z" strokeWidth="1.75" strokeLinejoin="round" fill="none" />
    case 'square':
      return <rect x="5" y="5" width="14" height="14" rx="1" strokeWidth="1.75" fill="none" />
    case 'chevron-right':
      return <path d="M9 6l6 6-6 6" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" fill="none" />
    case 'arrow-back':
      return <path d="M14 6l-6 6 6 6" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" fill="none" />
    case 'compass':
      return (
        <>
          <circle cx="12" cy="12" r="9" strokeWidth="1.75" fill="none" />
          <path d="M14.5 9.5L10 14l-2.5-4.5L14.5 9.5z" strokeWidth="1.5" strokeLinejoin="round" fill="none" />
        </>
      )
    case 'user':
      return (
        <>
          <circle cx="12" cy="8" r="4" strokeWidth="1.75" fill="none" />
          <path d="M5 20c1.5-3.5 4.5-5.5 7-5.5s5.5 2 7 5.5" strokeWidth="1.75" strokeLinecap="round" fill="none" />
        </>
      )
    case 'forum':
      return (
        <>
          <path d="M5 6h14v10H9l-4 3V6z" strokeWidth="1.75" strokeLinejoin="round" fill="none" />
        </>
      )
    case 'info':
      return (
        <>
          <circle cx="12" cy="12" r="9" strokeWidth="1.75" fill="none" />
          <path d="M12 10v6M12 7h.01" strokeWidth="1.75" strokeLinecap="round" />
        </>
      )
    case 'check':
      return <path d="M6 12l4 4 8-8" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" fill="none" />
    default:
      return null
  }
}

export default function DiscoveryIcon({ name, size = 24, className, stroke }: Props) {
  return (
    <svg
      className={clsx('discovery-icon', className)}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke={stroke ?? STROKE}
      aria-hidden
    >
      <IconPath name={name} />
    </svg>
  )
}

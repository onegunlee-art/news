interface Props {
  variant?: 'card' | 'profile' | 'community'
  className?: string
}

export default function GeometricGraphic({ variant = 'card', className = '' }: Props) {
  return (
    <svg
      className={`discovery-geometric ${variant} ${className}`.trim()}
      viewBox="0 0 80 80"
      aria-hidden
    >
      <polygon points="40,8 72,64 8,64" fill="none" stroke="#111" strokeWidth="2" />
      <polygon points="40,20 58,56 22,56" fill="none" stroke="#111" strokeWidth="1.5" />
      {variant === 'card' && <circle cx="58" cy="22" r="8" fill="#D72638" />}
      {variant === 'profile' && (
        <>
          <path d="M 10 70 Q 40 40 70 70" fill="none" stroke="#111" strokeWidth="2" />
          <circle cx="62" cy="18" r="4" fill="#D72638" />
        </>
      )}
      {variant === 'community' && <rect x="48" y="12" width="16" height="16" fill="#111" transform="rotate(15 56 20)" />}
    </svg>
  )
}

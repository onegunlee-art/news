interface Props {
  label: string
  unlocked?: boolean
}

export default function HexBadge({ label, unlocked = true }: Props) {
  const lines = label.split('\n')
  return (
    <div className={`discovery-hex-badge${unlocked ? '' : ' locked'}`}>
      <svg viewBox="0 0 64 72" className="discovery-hex-svg" aria-hidden>
        <polygon points="32,2 58,17 58,47 32,62 6,47 6,17" fill={unlocked ? '#fff' : '#f5f5f5'} stroke={unlocked ? '#D72638' : '#ccc'} strokeWidth="2" />
      </svg>
      <div className="discovery-hex-label">
        {lines.map((line) => (
          <span key={line}>{line}</span>
        ))}
      </div>
    </div>
  )
}

import { POLL_ICONS } from '../../fixtures/dummyFixtures'

interface Props {
  index: number
  label: string
  selected: boolean
  onSelect: () => void
  disabled?: boolean
  percent?: number
  showResult?: boolean
  isLeader?: boolean
}

export default function PollOptionRow({
  index,
  label,
  selected,
  onSelect,
  disabled,
  percent,
  showResult,
  isLeader,
}: Props) {
  const icon = POLL_ICONS[index] ?? '○'

  if (showResult && percent !== undefined) {
    return (
      <div className={`discovery-poll-result-row${isLeader ? ' leader' : ''}`}>
        <div className="discovery-poll-result-head">
          <span className="discovery-poll-icon">{icon}</span>
          <span className="discovery-poll-result-label">{label}</span>
          <span className="discovery-poll-result-pct">{percent}%</span>
        </div>
        <div className="discovery-poll-result-bar-track">
          <div className="discovery-poll-result-bar-fill" style={{ width: `${percent}%` }} />
        </div>
      </div>
    )
  }

  return (
    <button
      type="button"
      className={`discovery-poll-option-row${selected ? ' selected' : ''}${index === 0 && !selected ? ' first' : ''}`}
      onClick={onSelect}
      disabled={disabled}
    >
      <span className="discovery-poll-icon">{icon}</span>
      <span className="discovery-poll-option-text">{label}</span>
    </button>
  )
}

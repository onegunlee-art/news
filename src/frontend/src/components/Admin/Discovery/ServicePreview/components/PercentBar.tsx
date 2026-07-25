interface Props {
  percent: number
  leader?: boolean
}

export default function PercentBar({ percent, leader }: Props) {
  return (
    <div className={`discovery-percent-bar-track${leader ? ' leader' : ''}`}>
      <div className="discovery-percent-bar-fill" style={{ width: `${percent}%` }} />
    </div>
  )
}

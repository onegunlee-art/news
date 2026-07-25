interface Props {
  total: number
  current: number
}

export default function DotPager({ total, current }: Props) {
  if (total <= 1) return null
  return (
    <div className="discovery-dot-pager">
      {Array.from({ length: Math.min(total, 9) }, (_, i) => (
        <span key={i} className={`discovery-dot${i === current ? ' active' : ''}`} />
      ))}
    </div>
  )
}

import type { DiscoveryChange } from '../types'

interface Props {
  change: DiscoveryChange | null
  changeIndex: number
  total: number
  date: string
  onOpenDetail: () => void
  onOpenPoll: () => void
}

const categoryLabels: Record<string, string> = {
  geopolitics: '지정학',
  business: '비즈니스',
  tech: '기술',
  climate: '기후',
  other: '기타',
}

export default function DiscoveryHome({ change, changeIndex, total, date, onOpenDetail, onOpenPoll }: Props) {
  if (!change) {
    return <p style={{ color: '#666' }}>이 날짜에 표시할 변화가 없습니다.</p>
  }

  return (
    <div>
      <div style={{ fontSize: 12, color: '#888', marginBottom: 8 }}>{date} · {changeIndex + 1}/{total}</div>
      <div className="discovery-change-card">
        <span className="discovery-category">{categoryLabels[change.category] ?? change.category}</span>
        <h3>{change.title}</h3>
        <p style={{ fontSize: 14, lineHeight: 1.5, color: '#333' }}>{change.summary}</p>
        <button type="button" className="discovery-btn" style={{ width: '100%', marginTop: 8 }} onClick={onOpenDetail}>
          AI 브리핑 보기
        </button>
        {change.poll && (
          <button
            type="button"
            className="discovery-btn discovery-btn-secondary"
            style={{ width: '100%', marginTop: 8 }}
            onClick={onOpenPoll}
          >
            POLL 참여하기
          </button>
        )}
      </div>
      <p className="discovery-swipe-hint">↕ 날짜 이동 · ↔ 변화 순환</p>
    </div>
  )
}

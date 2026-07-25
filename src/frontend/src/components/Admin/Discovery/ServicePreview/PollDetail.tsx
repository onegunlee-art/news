import { useState } from 'react'
import type { DiscoveryChange, DiscoveryPollStats } from '../types'

interface Props {
  change: DiscoveryChange
  onBack: () => void
  onVote: (pollId: number, optionIdx: number) => Promise<DiscoveryPollStats>
}

export default function PollDetail({ change, onBack, onVote }: Props) {
  const poll = change.poll
  const [selected, setSelected] = useState<number | null>(null)
  const [stats, setStats] = useState<DiscoveryPollStats | null>(poll?.stats ?? null)
  const [voted, setVoted] = useState(false)

  if (!poll) {
    return <p>POLL 없음</p>
  }

  const submit = async (idx: number) => {
    if (!poll.id) return
    setSelected(idx)
    const next = await onVote(poll.id, idx)
    setStats(next)
    setVoted(true)
  }

  return (
    <div>
      <button type="button" className="discovery-btn discovery-btn-secondary" onClick={onBack} style={{ marginBottom: 12 }}>
        ← 홈
      </button>
      <h3 style={{ marginTop: 0, fontSize: 17 }}>{poll.question}</h3>
      {poll.options.map((opt, idx) => (
        <button
          key={opt}
          type="button"
          className={`discovery-poll-option${selected === idx ? ' selected' : ''}`}
          onClick={() => submit(idx)}
          disabled={voted}
        >
          {opt}
          {stats && voted && (
            <span style={{ float: 'right', color: '#888' }}>{stats.percents[idx]}%</span>
          )}
        </button>
      ))}
      {stats && (
        <div style={{ marginTop: 12, fontSize: 12, color: '#666' }}>
          {stats.total}명 참여 {stats.is_dummy ? '(더미)' : ''}
        </div>
      )}
      <div style={{ marginTop: 16, padding: 12, background: '#f9fafb', borderRadius: 10, fontSize: 13 }}>
        <strong>AI 의견 분석 (더미)</strong>
        <p style={{ margin: '6px 0 0' }}>
          참여자들은 단기적 영향과 장기적 파급 사이에서 균형 잡힌 시각을 보이고 있습니다.
          검수 단계에서는 더미 분석이 표시됩니다.
        </p>
      </div>
    </div>
  )
}

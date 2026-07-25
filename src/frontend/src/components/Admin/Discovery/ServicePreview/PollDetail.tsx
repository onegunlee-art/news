import { useMemo, useState } from 'react'
import MaterialIcon from '../../../Common/MaterialIcon'
import PollOptionRow from './components/PollOptionRow'
import { DUMMY_AI_OPINIONS, DUMMY_COMMENTS, DUMMY_POLL_STATS, pollDeadline } from './dummyPreviewData'
import type { DiscoveryChange, DiscoveryPollStats } from '../types'

interface Props {
  change: DiscoveryChange
  editionDate: string
  onBack: () => void
  initialStats?: DiscoveryPollStats | null
  initialSelectedIdx?: number | null
}

export default function PollDetail({
  change,
  editionDate,
  onBack,
  initialStats,
  initialSelectedIdx = null,
}: Props) {
  const poll = change.poll
  const [selectedIdx] = useState<number | null>(initialSelectedIdx)
  const stats = initialStats ?? poll?.stats ?? DUMMY_POLL_STATS
  const voted = true

  const leaderIdx = useMemo(() => {
    if (!stats?.percents?.length) return 0
    let max = 0
    stats.percents.forEach((p, i) => {
      if (p > (stats.percents[max] ?? 0)) max = i
    })
    return max
  }, [stats])

  if (!poll) {
    return <p>투표 없음</p>
  }

  return (
    <div className="discovery-poll-detail">
      <button type="button" className="discovery-back-btn" onClick={onBack}>← 홈</button>
      <h1 className="discovery-detail-title">{change.title}</h1>
      <p className="discovery-poll-meta">
        {stats.total.toLocaleString()}명이 참여했어요 · {pollDeadline(editionDate)} 마감
      </p>
      <p className="discovery-poll-question">{poll.question}</p>

      {poll.options.map((opt, idx) => (
        <PollOptionRow
          key={opt}
          index={idx}
          label={opt}
          selected={selectedIdx === idx}
          onSelect={() => {}}
          showResult={voted}
          percent={stats.percents[idx]}
          isLeader={idx === leaderIdx}
          disabled
        />
      ))}

      <div className="discovery-ai-card">
        <div className="discovery-ai-card-title">AI 의견 분석</div>
        <div className="discovery-ai-grid">
          <div className="discovery-ai-cell">
            <h4>{DUMMY_AI_OPINIONS.similar.title}</h4>
            <p>{DUMMY_AI_OPINIONS.similar.summary}</p>
            <footer>AI 요약 · {DUMMY_AI_OPINIONS.similar.count}개 의견 분석 &gt;</footer>
          </div>
          <div className="discovery-ai-cell">
            <h4>{DUMMY_AI_OPINIONS.different.title}</h4>
            <p>{DUMMY_AI_OPINIONS.different.summary}</p>
            <footer>AI 요약 · {DUMMY_AI_OPINIONS.different.count}개 의견 분석 &gt;</footer>
          </div>
        </div>
      </div>

      <div className="discovery-comments-header">
        <span>댓글 1,026</span>
        <span style={{ fontSize: 12, color: '#888' }}>최신순</span>
      </div>
      {DUMMY_COMMENTS.map((c) => (
        <div key={c.id} className="discovery-comment">
          <div className="discovery-comment-avatar" />
          <div className="discovery-comment-body">
            <div className="discovery-comment-meta">
              <strong>{c.user}</strong> · {c.time}
            </div>
            <p className="discovery-comment-text">{c.text}</p>
            <div className="discovery-comment-actions">
              <span>♥ {c.likes}</span>
              <span>⋮</span>
            </div>
          </div>
        </div>
      ))}

      <div className="discovery-comment-input">
        <input type="text" placeholder="의견을 남겨보세요" readOnly />
        <MaterialIcon name="send" size={20} />
      </div>
    </div>
  )
}

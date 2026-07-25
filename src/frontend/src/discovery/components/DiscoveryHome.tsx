import { useState } from 'react'
import SectionLabel from './ui/SectionLabel'
import SourceChipRow from './ui/SourceChipRow'
import GeometricGraphic from './ui/GeometricGraphic'
import PollOptionRow from './ui/PollOptionRow'
import DotPager from './ui/DotPager'
import type { DiscoveryChange } from '../types'

interface Props {
  change: DiscoveryChange | null
  changeIndex: number
  total: number
  onOpenBriefing: () => void
  onVoteSubmit: (pollId: number, optionIdx: number) => Promise<void>
}

export default function DiscoveryHome({
  change,
  changeIndex,
  total,
  onOpenBriefing,
  onVoteSubmit,
}: Props) {
  const [selectedIdx, setSelectedIdx] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (!change) {
    return <p className="discovery-change-summary">이 날짜에 표시할 변화가 없습니다.</p>
  }

  const poll = change.poll
  const counter = total > 0 ? `${changeIndex + 1}/${total}` : '0/0'

  const handleVote = async () => {
    if (!poll?.id || selectedIdx === null || submitting) return
    setSubmitting(true)
    try {
      await onVoteSubmit(poll.id, selectedIdx)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="discovery-home">
      <SectionLabel icon="●">오늘 세계에서 바뀐 것</SectionLabel>

      <article className="discovery-home-card">
        <GeometricGraphic variant="card" />
        <div className="discovery-home-card-inner">
          <h2 className="discovery-change-title">{change.title}</h2>
          <p className="discovery-change-summary">{change.summary}</p>
          <SourceChipRow sources={change.sources} />
        </div>
        <button type="button" className="discovery-btn-briefing" onClick={onOpenBriefing}>
          ✦ AI 브리핑
        </button>
      </article>

      {poll && (
        <section className="discovery-poll-block">
          <SectionLabel
            icon="◆"
            suffix={<span className="discovery-poll-counter">{counter}</span>}
          >
            오늘의 투표
          </SectionLabel>
          <p className="discovery-poll-question">{poll.question}</p>
          {poll.options.map((opt, idx) => (
            <PollOptionRow
              key={opt}
              index={idx}
              label={opt}
              selected={selectedIdx === idx}
              onSelect={() => setSelectedIdx(idx)}
            />
          ))}
          <button
            type="button"
            className="discovery-btn-vote"
            disabled={selectedIdx === null || submitting}
            onClick={handleVote}
          >
            투표하기
          </button>
          <DotPager total={total} current={changeIndex} />
          <p className="discovery-swipe-hint">좌우로 스와이프하여 다른 변화 보기</p>
        </section>
      )}
    </div>
  )
}

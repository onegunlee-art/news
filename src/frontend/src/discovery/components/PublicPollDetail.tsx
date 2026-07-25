import { useMemo, useState } from 'react'
import MaterialIcon from '../../components/Common/MaterialIcon'
import PollOptionRow from './ui/PollOptionRow'
import { DUMMY_AI_OPINIONS, pollDeadline } from '../fixtures/dummyFixtures'
import type { DiscoveryChange, DiscoveryPollStats } from '../types'

interface CommentItem {
  id: number
  body: string
  created_at: string
}

interface Props {
  change: DiscoveryChange
  editionDate: string
  onBack: () => void
  hasVoted: boolean
  stats: DiscoveryPollStats | null
  selectedIdx: number | null
  onVoteSubmit: (optionIdx: number) => Promise<void>
  comments: CommentItem[]
  onSubmitComment: (body: string) => Promise<void>
  onLoadMoreComments?: () => void | Promise<void>
  hasMoreComments?: boolean
}

function formatRelativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return '방금 전'
  if (mins < 60) return `${mins}분 전`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}시간 전`
  const days = Math.floor(hours / 24)
  return `${days}일 전`
}

export default function PublicPollDetail({
  change,
  editionDate,
  onBack,
  hasVoted,
  stats,
  selectedIdx: initialSelectedIdx,
  onVoteSubmit,
  comments,
  onSubmitComment,
  onLoadMoreComments,
  hasMoreComments,
}: Props) {
  const poll = change.poll
  const [selectedIdx, setSelectedIdx] = useState<number | null>(initialSelectedIdx)
  const [voting, setVoting] = useState(false)
  const [draft, setDraft] = useState('')
  const [submitting, setSubmitting] = useState(false)

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

  const handleVote = async () => {
    if (selectedIdx === null || voting) return
    setVoting(true)
    try {
      await onVoteSubmit(selectedIdx)
    } finally {
      setVoting(false)
    }
  }

  const handleCommentSubmit = async () => {
    const body = draft.trim()
    if (!body || submitting) return
    setSubmitting(true)
    try {
      await onSubmitComment(body)
      setDraft('')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="discovery-poll-detail">
      <button type="button" className="discovery-back-btn" onClick={onBack}>← 홈</button>
      <h1 className="discovery-detail-title">{change.title}</h1>
      {hasVoted && stats && (
        <p className="discovery-poll-meta">
          {stats.total.toLocaleString()}명이 참여했어요 · {pollDeadline(editionDate)} 마감
        </p>
      )}
      <p className="discovery-poll-question">{poll.question}</p>

      {!hasVoted ? (
        <>
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
            disabled={selectedIdx === null || voting}
            onClick={handleVote}
          >
            투표하기
          </button>
        </>
      ) : stats && (
        <>
          {poll.options.map((opt, idx) => (
            <PollOptionRow
              key={opt}
              index={idx}
              label={opt}
              selected={initialSelectedIdx === idx || selectedIdx === idx}
              onSelect={() => {}}
              showResult
              percent={stats.percents[idx] ?? 0}
              isLeader={idx === leaderIdx}
              disabled
            />
          ))}

          <div className="discovery-ai-card" data-dummy="ai-opinions">
            <div className="discovery-ai-card-title">AI 의견 분석 <span className="discovery-dummy-tag">더미</span></div>
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
            <span>댓글 {comments.length.toLocaleString()}</span>
            <span style={{ fontSize: 12, color: '#888' }}>최신순</span>
          </div>
          {comments.map((c) => (
            <div key={c.id} className="discovery-comment">
              <div className="discovery-comment-avatar" />
              <div className="discovery-comment-body">
                <div className="discovery-comment-meta">
                  <strong>참여자</strong> · {formatRelativeTime(c.created_at)}
                </div>
                <p className="discovery-comment-text">{c.body}</p>
              </div>
            </div>
          ))}
          {hasMoreComments && onLoadMoreComments && (
            <button type="button" className="discovery-btn-secondary discovery-btn" onClick={() => onLoadMoreComments()}>
              더 보기
            </button>
          )}

          <div className="discovery-comment-input">
            <input
              type="text"
              placeholder="의견을 남겨보세요 (1~500자)"
              value={draft}
              maxLength={500}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleCommentSubmit()
              }}
            />
            <button type="button" className="discovery-icon-btn" onClick={handleCommentSubmit} disabled={submitting || !draft.trim()} aria-label="댓글 등록">
              <MaterialIcon name="send" size={20} />
            </button>
          </div>
        </>
      )}
    </div>
  )
}

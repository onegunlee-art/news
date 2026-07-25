import { useMemo, useState } from 'react'
import SectionLabel from './ui/SectionLabel'
import GeometricGraphic from './ui/GeometricGraphic'
import DotPager from './ui/DotPager'
import MaterialIcon from '../../components/Common/MaterialIcon'
import { DUMMY_COMMUNITY_TOP, formatEditionDate } from '../fixtures/dummyFixtures'
import type { DiscoveryChange } from '../types'

interface Props {
  changes: DiscoveryChange[]
  editionDate: string
  onOpenPoll: (pollId: number) => void
}

export default function PublicCommunityTab({ changes, editionDate, onOpenPoll }: Props) {
  const polls = useMemo(
    () => changes.filter((c) => c.poll?.id).map((c) => ({ change: c, poll: c.poll! })),
    [changes],
  )
  const [carouselIdx, setCarouselIdx] = useState(0)
  const current = polls[carouselIdx]

  return (
    <div className="discovery-community">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="discovery-screen-title">커뮤니티</h1>
          <p className="discovery-screen-subtitle">오늘의 질문에 참여하고, 인기 토론을 살펴보세요</p>
        </div>
        <button type="button" className="discovery-icon-btn" aria-label="알림">
          <MaterialIcon name="notifications" size={20} />
        </button>
      </div>

      {current ? (
        <div className="discovery-community-card">
          <GeometricGraphic variant="community" />
          <SectionLabel icon="●" suffix={<span className="discovery-poll-counter">{carouselIdx + 1}/{polls.length}</span>}>
            오늘의 질문
          </SectionLabel>
          <p style={{ fontSize: 11, color: '#888', margin: '0 0 8px' }}>{formatEditionDate(editionDate)}</p>
          <p className="discovery-poll-question">{current.poll.question}</p>
          <p style={{ fontSize: 12, color: '#666' }}>
            {(current.poll.stats?.total ?? 0).toLocaleString()}명 참여
          </p>
          <button
            type="button"
            className="discovery-btn discovery-btn-vote"
            style={{ marginTop: 12 }}
            onClick={() => onOpenPoll(current.poll.id!)}
          >
            참여하기
          </button>
          <DotPager total={polls.length} current={carouselIdx} />
          <div style={{ display: 'flex', justifyContent: 'center', gap: 8, marginTop: 8 }}>
            <button type="button" className="discovery-btn-secondary discovery-btn" style={{ padding: '6px 12px' }} onClick={() => setCarouselIdx((i) => Math.max(0, i - 1))}>←</button>
            <button type="button" className="discovery-btn-secondary discovery-btn" style={{ padding: '6px 12px' }} onClick={() => setCarouselIdx((i) => Math.min(polls.length - 1, i + 1))}>→</button>
          </div>
        </div>
      ) : (
        <p className="discovery-change-summary">오늘 표시할 질문이 없습니다.</p>
      )}

      <h2 style={{ fontSize: 16, fontWeight: 800, margin: '0 0 12px' }}>
        🔥 인기 질문 TOP 5 <span className="discovery-dummy-tag">더미</span>
      </h2>
      <div data-dummy="community-top">
        {DUMMY_COMMUNITY_TOP.map((item) => (
          <div key={item.rank} className="discovery-top-list-item">
            <span className="discovery-top-rank">{item.rank}</span>
            <div>
              <div style={{ fontWeight: 600, fontSize: 14, lineHeight: 1.4 }}>{item.question}</div>
              <div style={{ fontSize: 12, color: '#888', marginTop: 4 }}>{item.participants.toLocaleString()}명 참여</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

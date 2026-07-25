import { useState } from 'react'
import SectionLabel from './components/SectionLabel'
import GeometricGraphic from './components/GeometricGraphic'
import DotPager from './components/DotPager'
import MaterialIcon from '../../../Common/MaterialIcon'
import { DUMMY_COMMUNITY_TOP } from './dummyPreviewData'

export default function CommunityTab() {
  const [carouselIdx, setCarouselIdx] = useState(0)
  const todayQuestion = DUMMY_COMMUNITY_TOP[carouselIdx]

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

      <div className="discovery-community-card">
        <GeometricGraphic variant="community" />
        <SectionLabel icon="●" suffix={<span className="discovery-poll-counter">{carouselIdx + 1}/9</span>}>
          오늘의 질문
        </SectionLabel>
        <p style={{ fontSize: 11, color: '#888', margin: '0 0 8px' }}>2026년 7월 25일</p>
        <p className="discovery-poll-question">{todayQuestion.question}</p>
        <p style={{ fontSize: 12, color: '#666' }}>{todayQuestion.participants.toLocaleString()}명 참여</p>
        <DotPager total={9} current={carouselIdx} />
        <div style={{ display: 'flex', justifyContent: 'center', gap: 8, marginTop: 8 }}>
          <button type="button" className="discovery-btn-secondary discovery-btn" style={{ padding: '6px 12px' }} onClick={() => setCarouselIdx((i) => Math.max(0, i - 1))}>←</button>
          <button type="button" className="discovery-btn-secondary discovery-btn" style={{ padding: '6px 12px' }} onClick={() => setCarouselIdx((i) => Math.min(8, i + 1))}>→</button>
        </div>
      </div>

      <h2 style={{ fontSize: 16, fontWeight: 800, margin: '0 0 12px' }}>🔥 인기 질문 TOP 5</h2>
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
  )
}

import MaterialIcon from '../../../Common/MaterialIcon'
import GeometricGraphic from './components/GeometricGraphic'
import HexBadge from './components/HexBadge'
import { DUMMY_BADGES, DUMMY_MYPAGE } from './dummyPreviewData'

export default function MyPageTab() {
  return (
    <div className="discovery-mypage">
      <div className="discovery-mypage-hero">
        <div className="discovery-mypage-avatar">
          <span className="discovery-mypage-avatar-dot" />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800, fontSize: 18 }}>{DUMMY_MYPAGE.nickname}</div>
          <span className="discovery-badge-chip">{DUMMY_MYPAGE.title}</span>
        </div>
        <GeometricGraphic variant="profile" />
      </div>

      <div className="discovery-stat-row">
        <div className="discovery-stat-cell">
          <strong>{DUMMY_MYPAGE.changesJoined}</strong>
          참여한 변화
          <MaterialIcon name="chevron_right" size={16} />
        </div>
        <div className="discovery-stat-cell">
          <strong>{DUMMY_MYPAGE.pollsJoined}</strong>
          참여한 투표
          <MaterialIcon name="chevron_right" size={16} />
        </div>
        <div className="discovery-stat-cell">
          <strong>{DUMMY_MYPAGE.commentsWritten}</strong>
          작성한 댓글
          <MaterialIcon name="chevron_right" size={16} />
        </div>
      </div>

      <div className="discovery-progress-block">
        <div className="discovery-progress-label">
          <span>{DUMMY_MYPAGE.journeyLabel}</span>
          <span>{DUMMY_MYPAGE.journeyPercent}%</span>
        </div>
        <div className="discovery-progress-track">
          <div className="discovery-progress-fill" style={{ width: `${DUMMY_MYPAGE.journeyPercent}%` }} />
        </div>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
        <span style={{ fontWeight: 800 }}>배지</span>
        <span style={{ fontSize: 12, color: '#888' }}>전체보기</span>
      </div>
      <div className="discovery-badge-grid">
        {DUMMY_BADGES.map((b) => (
          <HexBadge key={b.id} label={b.label} unlocked={b.unlocked} />
        ))}
      </div>

      <div className="discovery-menu-list">
        {[
          { label: '내 댓글', icon: 'chat_bubble_outline' },
          { label: '북마크', icon: 'bookmark_border' },
          { label: '알림', icon: 'notifications', dot: true },
          { label: '설정', icon: 'settings' },
        ].map((item) => (
          <button key={item.label} type="button" className="discovery-menu-item">
            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <MaterialIcon name={item.icon} size={20} />
              {item.label}
              {item.dot && <span style={{ width: 6, height: 6, background: '#D72638', borderRadius: '50%' }} />}
            </span>
            <MaterialIcon name="chevron_right" size={18} />
          </button>
        ))}
      </div>

      <div style={{ marginTop: 16, padding: 12, border: '1px solid #eee', borderRadius: 12, fontSize: 13 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span>다크모드</span>
          <span style={{ color: '#888' }}>OFF</span>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8 }}>
          <span>언어</span>
          <span>한국어</span>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8 }}>
          <span>푸시 알림</span>
          <span>ON</span>
        </div>
      </div>
    </div>
  )
}

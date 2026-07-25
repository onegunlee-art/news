import { useEffect, useState } from 'react'
import HexBadge from './ui/HexBadge'
import GeometricGraphic from './ui/GeometricGraphic'
import MaterialIcon from '../../components/Common/MaterialIcon'
import { DUMMY_BADGES } from '../fixtures/dummyFixtures'
import { useDiscoveryPublicApi } from '../hooks/useDiscoveryPublicApi'

export default function PublicMyPageTab() {
  const api = useDiscoveryPublicApi()
  const [stats, setStats] = useState({ votes_count: 0, comments_count: 0 })

  useEffect(() => {
    api.fetchMyStats().then(setStats).catch(() => {})
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="discovery-mypage">
      <div className="discovery-mypage-hero">
        <div className="discovery-mypage-avatar">
          <span className="discovery-mypage-avatar-dot" />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800, fontSize: 18 }}>관찰자</div>
          <span className="discovery-badge-chip">관찰자</span>
        </div>
        <GeometricGraphic variant="profile" />
      </div>

      <div className="discovery-stat-row">
        <div className="discovery-stat-cell">
          <strong>{stats.votes_count}</strong>
          참여한 투표
        </div>
        <div className="discovery-stat-cell">
          <strong>{stats.comments_count}</strong>
          작성한 댓글
        </div>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
        <span style={{ fontWeight: 800 }}>배지</span>
        <span style={{ fontSize: 12, color: '#888' }} data-dummy="badges">더미</span>
      </div>
      <div className="discovery-badge-grid" data-dummy="badges">
        {DUMMY_BADGES.map((b) => (
          <HexBadge key={b.id} label={b.label} unlocked={b.unlocked} />
        ))}
      </div>

      <div className="discovery-menu-list">
        <button type="button" className="discovery-menu-item">
          <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <MaterialIcon name="settings" size={20} />
            설정
          </span>
          <MaterialIcon name="chevron_right" size={18} />
        </button>
      </div>
    </div>
  )
}

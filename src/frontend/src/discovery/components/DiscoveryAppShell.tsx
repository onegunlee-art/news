import { ReactNode } from 'react'
import GistLogo from '../../components/Common/GistLogo'
import MaterialIcon from '../../components/Common/MaterialIcon'
import { formatEditionDate } from '../fixtures/dummyFixtures'
import type { PublicTab } from '../types'

interface Props {
  children: ReactNode
  activeTab: PublicTab
  onTabChange: (tab: PublicTab) => void
  onLogoTap: () => void
  onSearchTap?: () => void
  showHeader?: boolean
  editionDate?: string
}

const NAV_TABS: { id: PublicTab; label: string; icon: string }[] = [
  { id: 'community', label: '커뮤니티', icon: 'forum' },
  { id: 'mypage', label: '마이페이지', icon: 'person' },
  { id: 'search', label: '검색', icon: 'search' },
]

export default function DiscoveryAppShell({
  children,
  activeTab,
  onTabChange,
  onLogoTap,
  onSearchTap,
  showHeader = true,
  editionDate,
}: Props) {
  return (
    <div className="discovery-app-shell">
      {showHeader && (
        <header className="discovery-app-header">
          <button type="button" className="discovery-logo-btn" onClick={onLogoTap} aria-label="홈으로">
            <GistLogo link={false} size="header" className="discovery-logo" style={{ fontSize: '1.75rem' }} />
          </button>
          <div className="discovery-header-actions">
            <button type="button" className="discovery-icon-btn" onClick={onSearchTap ?? (() => onTabChange('search'))} aria-label="검색">
              <MaterialIcon name="search" size={22} />
            </button>
            <button type="button" className="discovery-icon-btn" aria-label="다크모드">
              <MaterialIcon name="dark_mode" size={22} />
            </button>
          </div>
        </header>
      )}
      {editionDate && activeTab === 'home' && (
        <div className="discovery-date-strip">{formatEditionDate(editionDate)}</div>
      )}
      <main className="discovery-app-body">{children}</main>
      <nav className="discovery-bottom-nav">
        {NAV_TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`discovery-bottom-nav-item${activeTab === tab.id ? ' active' : ''}`}
            onClick={() => onTabChange(tab.id)}
          >
            <MaterialIcon name={tab.icon} size={20} />
            <span>{tab.label}</span>
          </button>
        ))}
      </nav>
    </div>
  )
}

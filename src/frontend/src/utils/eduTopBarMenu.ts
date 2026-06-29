import type { EduTopBarMenuItem } from '../components/edu/EduTopBar'
import { hasEduOperatorSession } from './eduOperatorSession'

export function eduAuthedTopBarMenu(onLogout: () => void): EduTopBarMenuItem[] {
  const items: EduTopBarMenuItem[] = [
    { label: '홈', to: '/edu' },
    { label: '내 프로필', to: '/edu/profile' },
    { label: '논쟁 탐색', to: '/edu/explore' },
  ]

  if (hasEduOperatorSession()) {
    items.push({ label: '리포트 관리', to: '/edu/operator/reports', accent: true })
  }

  items.push({ label: '나가기', onClick: onLogout })
  return items
}

export function eduGuestTopBarMenu(onParticipate?: () => void): EduTopBarMenuItem[] {
  const items: EduTopBarMenuItem[] = [{ label: '논쟁 탐색', to: '/edu/explore' }]
  if (onParticipate) {
    items.unshift({ label: '참여하기', onClick: onParticipate, accent: true })
  }
  return items
}

/** 운영자 로그인만 한 경우(학생 토큰 없음) — 리포트 진입용 */
export function eduOperatorTopBarMenu(onLogout: () => void): EduTopBarMenuItem[] {
  return [
    { label: '리포트 관리', to: '/edu/operator/reports', accent: true },
    { label: 'EDU 홈', to: '/edu' },
    { label: '로그아웃', onClick: onLogout },
  ]
}

import type { EduTopBarMenuItem } from '../components/edu/EduTopBar'

export function eduAuthedTopBarMenu(onLogout: () => void): EduTopBarMenuItem[] {
  return [
    { label: '홈', to: '/edu' },
    { label: '내 프로필', to: '/edu/profile' },
    { label: '논쟁 탐색', to: '/edu/explore' },
    { label: '나가기', onClick: onLogout },
  ]
}

export function eduGuestTopBarMenu(onParticipate?: () => void): EduTopBarMenuItem[] {
  const items: EduTopBarMenuItem[] = [{ label: '논쟁 탐색', to: '/edu/explore' }]
  if (onParticipate) {
    items.unshift({ label: '참여하기', onClick: onParticipate, accent: true })
  }
  return items
}

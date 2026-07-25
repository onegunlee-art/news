import type { DiscoveryPollStats } from '../types'

export const POLL_ICONS = ['○', '△', '□', '✕'] as const

export const DUMMY_POLL_STATS: DiscoveryPollStats = {
  total: 127,
  counts: [44, 36, 28, 19],
  percents: [35, 28, 22, 15],
  is_dummy: true,
}

export const DUMMY_AI_OPINIONS = {
  similar: {
    title: '✦ 비슷한 시각',
    summary: '단기적으로는 시장·정책 변동성이 커질 수 있다는 의견이 많습니다.',
    count: 68,
  },
  different: {
    title: '✳ 다른 시각',
    summary: '장기적 구조 변화는 제한적이며, 점진적 조정으로 수렴할 것이라는 견해입니다.',
    count: 59,
  },
}

export const DUMMY_COMMENTS = [
  { id: 1, user: '글로벌옵저버', time: '2시간 전', text: '단기 충격보다 공급망 재편이 더 큰 변수로 보입니다.', likes: 24 },
  { id: 2, user: '정책워처', time: '4시간 전', text: '각국 대응 속도 차이가 시장 변동성을 키울 수 있어요.', likes: 18 },
  { id: 3, user: '테크애널리스트', time: '6시간 전', text: '기술 측면 파급은 아직 초기 단계로 판단됩니다.', likes: 11 },
]

export const DUMMY_COMMUNITY_TOP = [
  { rank: 1, question: '연준 금리 동결, 글로벌 자금 흐름에 미치는 영향은?', participants: 892 },
  { rank: 2, question: '중동 해상 봉쇄가 에너지 가격에 미치는 파급은?', participants: 756 },
  { rank: 3, question: 'EU AI 규제 강화, 빅테크 전략 변화는?', participants: 634 },
  { rank: 4, question: '중국 반도체 자립 가속, 공급망 재편 방향은?', participants: 521 },
  { rank: 5, question: '영국 재정 정책 전환, 유럽 경기에 주는 신호는?', participants: 487 },
]

export const DUMMY_RECENT_SEARCHES = ['연준 금리', '후티 봉쇄', '딥시크 AI', 'EU AI 규제']

export const DUMMY_BADGES = [
  { id: 'early', label: '얼리\n옵저버', unlocked: true },
  { id: 'global', label: '글로벌\n시티즌', unlocked: true },
  { id: 'ai', label: 'AI\n워처', unlocked: true },
  { id: 'policy', label: '정책\n탐험가', unlocked: false },
]

export const DUMMY_MYPAGE = {
  nickname: '관찰자',
  title: '관찰자',
  changesJoined: 42,
  pollsJoined: 28,
  commentsWritten: 15,
  journeyPercent: 34,
  journeyLabel: '세계를 이해하는 여정',
}

export function formatEditionDate(date: string): string {
  const d = new Date(`${date}T12:00:00`)
  return d.toLocaleDateString('ko-KR', { month: 'long', day: 'numeric', weekday: 'short' })
}

export function pollDeadline(date: string): string {
  const d = new Date(`${date}T23:59:59`)
  return d.toLocaleDateString('ko-KR', { month: 'long', day: 'numeric' })
}

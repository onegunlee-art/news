import { useSearchParams } from 'react-router-dom'
import QuestFlowChat from './QuestFlowChat'
import QuestFlowCards from './QuestFlowCards'
import { resolveEduCoachUiMode } from '../../constants/eduCoachUi'

/**
 * 코치 UI 라우터 — 카드형(기본) / 채팅형(보존)
 * ?ui=cards | ?ui=chat 또는 localStorage edu_coach_ui
 */
export default function QuestFlowPage() {
  const [searchParams] = useSearchParams()
  const mode = resolveEduCoachUiMode(searchParams)

  if (mode === 'chat') {
    return <QuestFlowChat />
  }

  return <QuestFlowCards />
}

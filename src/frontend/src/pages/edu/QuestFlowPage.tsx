import { lazy, Suspense, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduCoachWaitingPanel from '../../components/edu/EduCoachWaitingPanel'
import { resolveEduCoachUiMode } from '../../constants/eduCoachUi'
import { resolveNarrativeSurface } from '../../constants/eduNarrativeBridge'
import { eduApi } from '../../services/eduApi'

const QuestFlowChat = lazy(() => import('./QuestFlowChat'))
const QuestFlowCards = lazy(() => import('./QuestFlowCards'))
const QuestFlowNarrativeBridge = lazy(() => import('../../components/edu/QuestFlowNarrativeBridge'))
const QuestFlowNarrativeV2 = lazy(() => import('../../components/edu/QuestFlowNarrativeV2'))

type Surface = 'loading' | 'v2' | 'v1' | 'default'

export default function QuestFlowPage() {
  const [searchParams] = useSearchParams()
  const coachUi = resolveEduCoachUiMode(searchParams)
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const [surface, setSurface] = useState<Surface>('loading')

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        let sid = ''
        if (questIdParam) {
          sid = (await eduApi.startSession(questIdParam)).session_id
        } else {
          const today = await eduApi.todayQuest()
          if (!today.quest) {
            if (!cancelled) setSurface('default')
            return
          }
          sid = today.active_session?.session_id ?? today.existing_session?.session_id ?? ''
          if (!sid) sid = (await eduApi.startSession(today.quest.quest_id)).session_id
        }
        if (!sid) {
          if (!cancelled) setSurface('default')
          return
        }
        const state = await eduApi.getSessionState(sid)
        if (!cancelled) setSurface(resolveNarrativeSurface(state.quest))
      } catch {
        if (!cancelled) setSurface('default')
      }
    })()
    return () => {
      cancelled = true
    }
  }, [questIdParam])

  if (surface === 'loading') {
    return <EduCoachWaitingPanel label="퀘스트 불러오는 중…" />
  }

  return (
    <Suspense fallback={<EduCoachWaitingPanel label="화면 준비 중…" />}>
      {surface === 'v2' ? (
        <QuestFlowNarrativeV2 />
      ) : surface === 'v1' ? (
        <QuestFlowNarrativeBridge />
      ) : coachUi === 'chat' ? (
        <QuestFlowChat />
      ) : (
        <QuestFlowCards />
      )}
    </Suspense>
  )
}

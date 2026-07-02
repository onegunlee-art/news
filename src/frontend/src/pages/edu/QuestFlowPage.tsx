import { lazy, Suspense, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduCoachWaitingPanel from '../../components/edu/EduCoachWaitingPanel'
import { questUsesNarrativeBridge } from '../../constants/eduNarrativeBridge'
import { resolveEduCoachUiMode } from '../../constants/eduCoachUi'
import { eduApi } from '../../services/eduApi'

const QuestFlowChat = lazy(() => import('./QuestFlowChat'))
const QuestFlowCards = lazy(() => import('./QuestFlowCards'))
const QuestFlowNarrativeBridge = lazy(() => import('../../components/edu/QuestFlowNarrativeBridge'))

type Surface = 'loading' | 'narrative' | 'default'

/**
 * 코치 UI 라우터 — 630 narrative bridge / 카드형(기본) / 채팅형(보존)
 */
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
          const started = await eduApi.startSession(questIdParam)
          sid = started.session_id
        } else {
          const today = await eduApi.todayQuest()
          if (!today.quest) {
            if (!cancelled) setSurface('default')
            return
          }
          const existing = today.active_session || today.existing_session
          sid = existing?.session_id ?? ''
          if (!sid) {
            const started = await eduApi.startSession(today.quest.quest_id)
            sid = started.session_id
          }
        }
        if (!sid) {
          if (!cancelled) setSurface('default')
          return
        }
        const state = await eduApi.getSessionState(sid)
        if (!cancelled) {
          setSurface(questUsesNarrativeBridge(state.quest) ? 'narrative' : 'default')
        }
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
      {surface === 'narrative' ? (
        <QuestFlowNarrativeBridge />
      ) : coachUi === 'chat' ? (
        <QuestFlowChat />
      ) : (
        <QuestFlowCards />
      )}
    </Suspense>
  )
}

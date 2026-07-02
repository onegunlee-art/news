import { lazy, Suspense, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduCoachWaitingPanel from '../../components/edu/EduCoachWaitingPanel'
import { resolveEduCoachUiMode } from '../../constants/eduCoachUi'
import {
  EDU_NARRATIVE_BRIDGE_QUEST_CODE,
  resolveQuestFlowSurface,
  type NarrativeSurface,
} from '../../constants/eduNarrativeBridge'
import { eduApi } from '../../services/eduApi'

const QuestFlowChat = lazy(() => import('./QuestFlowChat'))
const QuestFlowCards = lazy(() => import('./QuestFlowCards'))
const QuestFlowNarrativeBridge = lazy(() => import('../../components/edu/QuestFlowNarrativeBridge'))
const QuestFlowNarrativeV2 = lazy(() => import('../../components/edu/QuestFlowNarrativeV2'))

type Surface = 'loading' | NarrativeSurface

function surfaceFromParams(searchParams: URLSearchParams): Surface {
  const hinted = resolveQuestFlowSurface({
    coachModeParam: searchParams.get('coach_mode'),
    questCodeParam: searchParams.get('quest_code'),
  })
  return hinted === 'default' ? 'loading' : hinted
}

export default function QuestFlowPage() {
  const [searchParams] = useSearchParams()
  const coachUi = resolveEduCoachUiMode(searchParams)
  const questIdParam = searchParams.get('quest_id')?.trim() ?? ''
  const coachModeParam = searchParams.get('coach_mode')
  const questCodeParam = searchParams.get('quest_code')
  const initialSurface = useMemo(() => surfaceFromParams(searchParams), [searchParams])
  const [surface, setSurface] = useState<Surface>(initialSurface)

  useEffect(() => {
    if (initialSurface !== 'loading') {
      setSurface(initialSurface)
    }
  }, [initialSurface])

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        let sid = ''
        let questCode = questCodeParam
        let coachMode = coachModeParam

        if (questIdParam) {
          sid = (await eduApi.startSession(questIdParam)).session_id
        } else {
          const today = await eduApi.todayQuest()
          if (!today.quest) {
            if (!cancelled) setSurface(prev => (prev === 'loading' ? 'default' : prev))
            return
          }
          questCode = questCode ?? today.quest.quest_code
          coachMode = coachMode ?? today.quest.coach_mode ?? null
          sid = today.active_session?.session_id ?? today.existing_session?.session_id ?? ''
          if (!sid) sid = (await eduApi.startSession(today.quest.quest_id)).session_id
        }

        if (!sid) {
          if (!cancelled) setSurface(prev => (prev === 'loading' ? 'default' : prev))
          return
        }

        const state = await eduApi.getSessionState(sid)
        const resolved = resolveQuestFlowSurface({
          quest: state.quest,
          blueprint: state.blueprint,
          coachModeParam: coachMode,
          questCodeParam: questCode ?? state.quest?.quest_code ?? null,
        })

        if (!cancelled) {
          setSurface(resolved)
        }
      } catch {
        if (cancelled) return
        const fallback = resolveQuestFlowSurface({
          coachModeParam: coachModeParam,
          questCodeParam: questCodeParam ?? EDU_NARRATIVE_BRIDGE_QUEST_CODE,
        })
        setSurface(fallback === 'default' ? 'default' : fallback)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [questIdParam, coachModeParam, questCodeParam])

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

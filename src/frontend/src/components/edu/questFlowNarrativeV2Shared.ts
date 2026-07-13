import { useEffect, useState } from 'react'
import type {
  EduBlueprint,
  EduChatResponse,
  EduDialogueTurn,
  EduLevelUpPayload,
  EduQuest,
  EduThoughtBoardSlot,
  EduTierProgress,
  EduXpBreakdownLine,
} from '../../services/eduApi'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import type { EssayArtifact } from './EssayRevealCard'

export const NARRATIVE_V2_PAGE_MAX = 'max-w-2xl'

export type NarrativeV2Choice = { id: string; label: string }

export function resolveNarrativeV2Choices(
  res: Pick<EduChatResponse, 'narrative_choices' | 'choice_question' | 'options'>
): NarrativeV2Choice[] {
  if (Array.isArray(res.narrative_choices) && res.narrative_choices.length > 0) {
    return res.narrative_choices
  }
  if (res.choice_question && Array.isArray(res.options)) {
    return res.options.map((label, i) => ({ id: `opt_${i}`, label }))
  }
  return []
}

export function resolveNarrativeV2InputMode(
  res: Pick<EduChatResponse, 'narrative_v2_input_mode' | 'blueprint'>
): string {
  const direct = (res.narrative_v2_input_mode ?? '').trim()
  if (direct !== '') return direct
  return (res.blueprint?.narrative_v2_input_mode ?? '').trim()
}

export function resolveCoachPrompt(dialogue: EduDialogueTurn[], fallback = ''): string {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') {
      const text = (dialogue[i].content ?? '').trim()
      if (text !== '') return text
    }
  }
  return fallback.trim()
}

export function resolveCoachPromptFromApi(
  res: Pick<EduChatResponse, 'assistant_message' | 'choice_question_text'> & {
    dialogue?: EduDialogueTurn[]
  }
): string {
  const fromDialogue = resolveCoachPrompt(res.dialogue ?? [])
  if (fromDialogue !== '') return fromDialogue
  const fromAssistant = (res.assistant_message ?? '').trim()
  if (fromAssistant !== '') return fromAssistant
  return (res.choice_question_text ?? '').trim()
}

export function lastAssistantIndex(dialogue: EduDialogueTurn[]): number {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'assistant') return i
  }
  return -1
}

export function lastStudentAnswer(dialogue: EduDialogueTurn[]): string | null {
  for (let i = dialogue.length - 1; i >= 0; i--) {
    if (dialogue[i].role === 'student') return dialogue[i].content ?? null
  }
  return null
}

export function useMobileCompactLayout(): boolean {
  const [mobile, setMobile] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches
  )

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 639px)')
    const onChange = () => setMobile(mq.matches)
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [])

  return mobile
}

export function useVisualViewportLayout(): {
  viewportHeight: number | null
  viewportOffsetTop: number
  keyboardInset: number
} {
  const [layout, setLayout] = useState({
    viewportHeight: null as number | null,
    viewportOffsetTop: 0,
    keyboardInset: 0,
  })

  useEffect(() => {
    const vv = window.visualViewport
    if (!vv) return
    const update = () => {
      setLayout({
        viewportHeight: vv.height,
        viewportOffsetTop: vv.offsetTop,
        keyboardInset: Math.max(0, window.innerHeight - vv.height - vv.offsetTop),
      })
    }
    vv.addEventListener('resize', update)
    vv.addEventListener('scroll', update)
    update()
    return () => {
      vv.removeEventListener('resize', update)
      vv.removeEventListener('scroll', update)
    }
  }, [])

  return layout
}

export type QuestFlowNarrativeV2ViewProps = {
  quest: EduQuest | null
  sessionId: string
  dialogue: EduDialogueTurn[]
  board: EduThoughtBoardSlot[]
  blueprint: EduBlueprint | null
  choices: NarrativeV2Choice[]
  inputMode: string
  textInput: string
  setTextInput: (v: string) => void
  inputFocused: boolean
  setInputFocused: (v: boolean) => void
  pulseLayer: string | null
  boardCollapsed: boolean
  toggleBoardCollapsed: () => void
  turnCount: number
  progressPct: number
  phase: string
  sending: boolean
  assembling: boolean
  composeReady: boolean
  completed: boolean
  error: string
  essay: EssayArtifact | null
  setEssay: (e: EssayArtifact | null) => void
  xpGained: number
  xpBreakdown: EduXpBreakdownLine[]
  tier: EduTierProgress | null
  coachLevel: EduCoachLevelInfo
  levelUp: EduLevelUpPayload | null
  todayComboCount: number
  playEssayReveal: boolean
  setPlayEssayReveal: (v: boolean) => void
  saveStatus: 'idle' | 'saving' | 'saved' | 'error'
  displayName: string | null
  filledCount: number
  coachPrompt: string
  inputRef: React.Ref<HTMLTextAreaElement>
  handleChoice: (choice: NarrativeV2Choice) => void
  handleTextSubmit: () => void
  handleAnimComplete: () => void
  persistEssay: (data: EssayArtifact) => Promise<void>
  viewportHeight: number | null
  viewportOffsetTop: number
  keyboardInset: number
}

export function resolveCurrentJourneyLayerId(
  board: EduThoughtBoardSlot[],
  pulseLayer: string | null
): string | null {
  if (pulseLayer) return pulseLayer
  const firstEmpty = board.find(s => !s.filled)
  if (firstEmpty) return firstEmpty.layer_id
  const last = board[board.length - 1]
  return last?.layer_id ?? null
}

export function journeyLayerStatus(
  slot: EduThoughtBoardSlot,
  currentLayerId: string | null
): 'done' | 'current' | 'future' {
  if (slot.filled) return 'done'
  if (currentLayerId === slot.layer_id) return 'current'
  return 'future'
}

const LAYER_CIRCLES = ['①', '②', '③', '④', '⑤', '⑥']

export function layerCircle(index: number): string {
  return LAYER_CIRCLES[index - 1] ?? String(index)
}

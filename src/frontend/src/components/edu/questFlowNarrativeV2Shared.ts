import type { Ref } from 'react'
import type { EssayArtifact } from './EssayRevealCard'
import type { EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import type {
  EduDialogueTurn,
  EduLevelUpPayload,
  EduQuest,
  EduThoughtBoardSlot,
  EduTierProgress,
  EduXpBreakdownLine,
} from '../../services/eduApi'

export type QuestFlowChoice = { id: string; label: string }

export type QuestFlowCoachCard = {
  question: string
  snippets: Array<{ display: string; value: string }>
}

export type QuestFlowNarrativeV2ViewProps = {
  quest: EduQuest | null
  sessionId: string
  dialogue: EduDialogueTurn[]
  board: EduThoughtBoardSlot[]
  choices: QuestFlowChoice[]
  inputMode: string
  textInput: string
  setTextInput: (value: string) => void
  pulseLayer: string | null
  boardCollapsed: boolean
  toggleBoardCollapsed: () => void
  openBoardPanel: (layerId?: string) => void
  boardFocusLayer: string | null
  turnCount: number
  progressPct: number
  phase: string
  narrativeV2Node: string
  sending: boolean
  assembling: boolean
  completed: boolean
  error: string
  coachIndex: number
  cardContent: QuestFlowCoachCard
  cardParagraphs: string[]
  cardKey: string
  keyboardOpen: boolean
  showTextInput: boolean
  waitingLabel: string
  displayName: string | null
  filledCount: number
  mobileCompact: boolean
  viewportHeight: number | null
  viewportOffsetTop: number
  keyboardInset: number
  inputFocused: boolean
  setInputFocused: (focused: boolean) => void
  inputRef: Ref<HTMLTextAreaElement>
  onChoice: (choice: QuestFlowChoice) => void
  onTextSubmit: () => void
}

export type QuestFlowCompletionProps = {
  quest: EduQuest | null
  board: EduThoughtBoardSlot[]
  essay: EssayArtifact
  setEssay: (essay: EssayArtifact) => void
  displayName: string | null
  xpGained: number
  xpBreakdown: EduXpBreakdownLine[]
  levelUp: EduLevelUpPayload | null
  tier: EduTierProgress | null
  coachLevel: EduCoachLevelInfo
  todayComboCount: number
  playEssayReveal: boolean
  setPlayEssayReveal: (v: boolean) => void
  saveStatus: 'idle' | 'saving' | 'saved' | 'error'
}

export const QUEST_FLOW_PAGE_MAX = 'max-w-2xl'

import type { EduChatResponse, EduDialogueTurn } from '../../services/eduApi'
import { coachMessageHasSnippet, parseCoachAssistantMessage } from '../../utils/eduCoachMessageParse'
import type { QuestFlowChoice } from './questFlowNarrativeV2Shared'

export function resolveChoices(
  res: Pick<EduChatResponse, 'narrative_choices' | 'choice_question' | 'options'>
): QuestFlowChoice[] {
  if (Array.isArray(res.narrative_choices) && res.narrative_choices.length > 0) {
    return res.narrative_choices
  }
  if (res.choice_question && Array.isArray(res.options)) {
    return res.options.map((label, i) => ({ id: `opt_${i}`, label }))
  }
  return []
}

export function resolveInputMode(
  res: Pick<EduChatResponse, 'narrative_v2_input_mode' | 'blueprint'>
): string {
  const direct = (res.narrative_v2_input_mode ?? '').trim()
  if (direct !== '') return direct
  const fromBp = (res.blueprint?.narrative_v2_input_mode ?? '').trim()
  return fromBp
}

export function parseCoachCardContent(content: string): {
  question: string
  snippets: Array<{ display: string; value: string }>
} {
  if (!coachMessageHasSnippet(content)) {
    return { question: content, snippets: [] }
  }
  const segments = parseCoachAssistantMessage(content)
  const question = segments
    .filter(s => s.type === 'text')
    .map(s => s.value)
    .join('\n\n')
    .trim()
  const snippets = segments
    .filter(s => s.type === 'snippet')
    .map(s => ({ display: s.display, value: s.value }))
  return { question: question || content, snippets }
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

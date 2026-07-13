import CoachMessageText from '../CoachMessageText'
import EduArticleSnippetCard from '../EduArticleSnippetCard'
import EduPcScanBarIndicator from './EduPcScanBarIndicator'
import type { EduDialogueTurn } from '../../../services/eduApi'
import { eduPc } from '../../../constants/eduPcRedesignTheme'
import {
  coachMessageHasSnippet,
  parseCoachAssistantMessage,
  splitCoachParagraphs,
} from '../../../utils/eduCoachMessageParse'

type Snippet = { display: string; value: string }

function parseSnippets(content: string): { text: string; snippets: Snippet[] } {
  if (!coachMessageHasSnippet(content)) {
    return { text: content, snippets: [] }
  }
  const segments = parseCoachAssistantMessage(content)
  const text = segments
    .filter(s => s.type === 'text')
    .map(s => s.value)
    .join('\n\n')
    .trim()
  const snippets = segments
    .filter(s => s.type === 'snippet')
    .map(s => ({ display: s.display, value: s.value }))
  return { text: text || content, snippets }
}

type Props = {
  dialogue: EduDialogueTurn[]
  sending: boolean
  assembling: boolean
}

export default function EduPcCoachDialogue({ dialogue, sending, assembling }: Props) {
  return (
    <div className="flex flex-col flex-1 min-h-0">
      <div
        className="shrink-0 px-5 py-2 text-xs border-b"
        style={{
          borderColor: eduPc.borderSubtle,
          color: eduPc.inkMuted,
          fontFamily: eduPc.fontBody,
        }}
      >
        AI 코치와 대화 중 · 답을 주지 않고 되묻습니다
      </div>

      <div className="flex-1 min-h-0 overflow-y-auto px-5 py-4 space-y-4">
        {dialogue.map((turn, i) => {
          if (turn.role === 'student') {
            return (
              <div key={`d-${i}`} className="flex justify-end">
                <div
                  className="max-w-[85%] rounded-[15px] rounded-br-sm px-4 py-3 text-sm leading-relaxed"
                  style={{
                    backgroundColor: eduPc.orangeDim,
                    border: `1px solid ${eduPc.orange}`,
                    color: eduPc.ink,
                    fontFamily: eduPc.fontBody,
                  }}
                >
                  {turn.content}
                </div>
              </div>
            )
          }
          const parsed = parseSnippets(turn.content ?? '')
          const paragraphs = splitCoachParagraphs(parsed.text)
          return (
            <div key={`d-${i}`} className="max-w-[92%] space-y-2">
              {paragraphs.map((p, pi) => (
                <p
                  key={`p-${pi}`}
                  className="text-left leading-relaxed"
                  style={{
                    fontFamily: eduPc.fontHeadline,
                    fontSize: '19px',
                    color: eduPc.ink,
                  }}
                >
                  <CoachMessageText text={p} />
                </p>
              ))}
              {parsed.snippets.length > 0 && (
                <div className="space-y-2 mt-2">
                  {parsed.snippets.map((snip, si) => (
                    <EduArticleSnippetCard key={`snip-${i}-${si}`} text={snip.value} display={snip.display} />
                  ))}
                </div>
              )}
            </div>
          )
        })}

        {sending && !assembling ? <EduPcScanBarIndicator /> : null}
      </div>
    </div>
  )
}

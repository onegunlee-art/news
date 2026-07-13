import type { NarrativeV2Choice } from '../questFlowNarrativeV2Shared'
import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  choices: NarrativeV2Choice[]
  showTextInput: boolean
  textInput: string
  setTextInput: (v: string) => void
  setInputFocused: (v: boolean) => void
  inputRef: React.Ref<HTMLTextAreaElement>
  sending: boolean
  assembling: boolean
  onChoice: (c: NarrativeV2Choice) => void
  onTextSubmit: () => void
}

export default function EduPcChoiceFooter({
  choices,
  showTextInput,
  textInput,
  setTextInput,
  setInputFocused,
  inputRef,
  sending,
  assembling,
  onChoice,
  onTextSubmit,
}: Props) {
  return (
    <footer
      className="shrink-0 border-t px-5 py-4"
      style={{ borderColor: eduPc.border, backgroundColor: 'rgba(7,7,7,0.95)' }}
    >
      {showTextInput ? (
        <div className="space-y-3 max-w-2xl mx-auto">
          <textarea
            ref={inputRef}
            value={textInput}
            onChange={e => setTextInput(e.target.value)}
            onFocus={() => setInputFocused(true)}
            onBlur={() => window.setTimeout(() => setInputFocused(false), 100)}
            rows={4}
            placeholder="네 생각을 한두 문장으로 써 봐…"
            disabled={sending || assembling}
            className="w-full resize-none rounded-[11px] px-4 py-3 text-sm focus:outline-none focus:ring-2"
            style={{
              fontFamily: eduPc.fontBody,
              backgroundColor: eduPc.cardBg,
              border: `1px solid ${eduPc.border}`,
              color: eduPc.ink,
            }}
          />
          <button
            type="button"
            disabled={sending || assembling || !textInput.trim()}
            onClick={onTextSubmit}
            className="w-full py-3.5 rounded-[11px] font-bold text-sm transition-transform active:scale-[0.98] disabled:opacity-40"
            style={{
              backgroundColor: eduPc.orange,
              color: '#fff',
              fontFamily: eduPc.fontBody,
            }}
          >
            {sending ? '보내는 중…' : '내 결론 보내기'}
          </button>
        </div>
      ) : choices.length > 0 ? (
        <div className="space-y-2.5 max-w-2xl mx-auto" role="group" aria-label="선택지">
          {choices.map(c => (
            <button
              key={c.id}
              type="button"
              disabled={sending || assembling}
              onClick={() => onChoice(c)}
              className="edu-pc-choice-btn w-full text-left py-3.5 px-4 rounded-[11px] font-bold text-sm border transition-all disabled:opacity-40"
              style={{
                fontFamily: eduPc.fontBody,
                borderColor: eduPc.border,
                backgroundColor: eduPc.cardBg,
                color: eduPc.ink,
              }}
            >
              {c.label}
            </button>
          ))}
        </div>
      ) : null}
    </footer>
  )
}

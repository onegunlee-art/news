import { Link } from 'react-router-dom'
import type { EduThoughtBoardSlot } from '../../services/eduApi'
import { eduPc, eduPcClasses } from '../../constants/eduPcRedesignTheme'
import type { EssayArtifact } from './EssayRevealCard'

const LAYER_CIRCLES = ['①', '②', '③', '④', '⑤', '⑥']

type Props = {
  essay: EssayArtifact
  board: EduThoughtBoardSlot[]
  onChange: (essay: EssayArtifact) => void
  saveStatus: 'idle' | 'saving' | 'saved' | 'error'
}

function layerCircle(index: number): string {
  return LAYER_CIRCLES[index - 1] ?? String(index)
}

/** PC 완료 — LLM full_text + 생각판 출처 */
export default function EduPcEssayCompletionPanel({ essay, board, onChange, saveStatus }: Props) {
  const fullText = essay.full_text ?? ''

  return (
    <div className="flex-1 min-h-0 flex flex-col p-6 gap-4 overflow-hidden">
      <div className="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-5 overflow-hidden">
        <div className="min-h-0 flex flex-col">
          <p className="text-sm font-bold mb-2" style={{ color: eduPc.primary }}>
            나만의 글
          </p>
          <textarea
            value={fullText}
            onChange={e => onChange({ ...essay, full_text: e.target.value })}
            className={`flex-1 min-h-[240px] w-full resize-none rounded-xl border px-4 py-3 text-sm leading-relaxed ${eduPcClasses.textKo}`}
            style={{
              borderColor: eduPc.borderStrong,
              backgroundColor: eduPc.surface,
              color: eduPc.text,
              fontFamily: eduPc.fontSerif,
            }}
          />
          <p className="text-xs mt-2" style={{ color: eduPc.textMuted }}>
            {saveStatus === 'saved' && '✓ 자동 저장됨'}
            {saveStatus === 'saving' && '저장 중…'}
            {saveStatus === 'error' && '저장 실패'}
          </p>
        </div>
        <div className="min-h-0 flex flex-col overflow-y-auto">
          <p className="text-sm font-bold mb-2" style={{ color: eduPc.primary }}>
            이 글의 출처 — 내 생각판
          </p>
          <div className="space-y-2">
            {board.map(slot => (
              <div
                key={slot.layer_id}
                className="rounded-lg border px-3 py-2"
                style={{ borderColor: eduPc.borderStrong, backgroundColor: eduPc.surface }}
              >
                <p className="text-xs font-bold" style={{ color: eduPc.primary }}>
                  {layerCircle(slot.index)} {slot.label}
                </p>
                <p className={`text-sm mt-1 ${eduPcClasses.textKo}`} style={{ color: eduPc.textMuted }}>
                  {slot.filled && slot.text.trim() ? slot.text : '—'}
                </p>
              </div>
            ))}
          </div>
          <p className="text-xs mt-4 leading-relaxed" style={{ color: eduPc.textDim }}>
            AI가 대신 쓴 글이 아닙니다. 네가 탐구하며 쌓은 생각을 글로 엮었어요.
          </p>
        </div>
      </div>
      <div className="shrink-0 flex flex-wrap gap-3 justify-end pt-2 border-t" style={{ borderColor: eduPc.border }}>
        <Link
          to="/edu"
          className="rounded-lg px-5 py-2.5 text-sm font-bold border"
          style={{ borderColor: eduPc.borderStrong, color: eduPc.text }}
        >
          처음부터
        </Link>
        <Link
          to="/edu"
          className="rounded-lg px-5 py-2.5 text-sm font-bold"
          style={{ backgroundColor: eduPc.primary, color: eduPc.text }}
        >
          글 제출하기
        </Link>
      </div>
    </div>
  )
}

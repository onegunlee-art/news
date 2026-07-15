import { eduPc } from '../../constants/eduPcRedesignTheme'
import type { EduThoughtBoardSlot } from '../../services/eduApi'

type Props = {
  phase: string
  narrativeV2Node: string
  turnCount: number
  filledCount: number
  board: EduThoughtBoardSlot[]
  coachLevel: number
  inputMode: string
  sending: boolean
  assembling: boolean
  visible: boolean
}

/** PC — 기술 투명 모드 (?tech_transparency=1 또는 T) */
export default function EduPcTechTransparencyPanel({
  phase,
  narrativeV2Node,
  turnCount,
  filledCount,
  board,
  coachLevel,
  inputMode,
  sending,
  assembling,
  visible,
}: Props) {
  if (!visible) return null

  const filledLayers = board.filter(s => s.filled).map(s => `${s.index}:${s.layer_id}`).join(', ')

  return (
    <div
      className="fixed bottom-4 right-4 z-[60] max-w-xs rounded-lg border px-3 py-2 text-[11px] font-mono leading-relaxed shadow-lg"
      style={{
        borderColor: eduPc.borderStrong,
        backgroundColor: 'rgba(7,7,7,0.92)',
        color: eduPc.textMuted,
      }}
      aria-label="기술 투명 모드"
    >
      <p style={{ color: eduPc.primary }} className="font-bold mb-1">
        tech_transparency
      </p>
      <p>FSM phase: {phase || '—'}</p>
      <p>node: {narrativeV2Node || '—'}</p>
      <p>layer filled: {filledCount}/6 [{filledLayers || '—'}]</p>
      <p>turn: {turnCount}</p>
      <p>input_mode: {inputMode || 'choice'}</p>
      <p>coach_level: L{coachLevel}</p>
      <p>LLM: {sending ? 'chat…' : assembling ? 'compose…' : 'idle'}</p>
    </div>
  )
}

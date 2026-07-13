import type { EduBlueprint, EduDialogueTurn } from '../../../services/eduApi'
import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  blueprint: EduBlueprint | null
  phase: string
  dialogue: EduDialogueTurn[]
  filledCount: number
  turnCount: number
  coachLevel: number | undefined
  visible: boolean
}

export default function EduPcTechTransparencyPanel({
  blueprint,
  phase,
  dialogue,
  filledCount,
  turnCount,
  coachLevel,
  visible,
}: Props) {
  if (!visible) return null

  const lastAgent = [...dialogue].reverse().find(t => t.role === 'assistant')
  const inputQuality = (blueprint as Record<string, unknown> | null)?.narrative_v2_input_quality

  return (
    <div
      className="fixed bottom-4 right-4 z-50 max-w-sm rounded-[11px] border p-3 font-mono text-[11px] leading-relaxed shadow-lg"
      style={{
        borderColor: eduPc.orange,
        backgroundColor: 'rgba(12,12,12,0.96)',
        color: eduPc.inkMuted,
      }}
      aria-label="기술 투명 모드"
    >
      <p className="font-bold mb-1.5" style={{ color: eduPc.orange }}>
        Tech transparency
      </p>
      <p>FSM node: {blueprint?.narrative_v2_node ?? '—'}</p>
      <p>phase: {phase}</p>
      <p>layer: thought_board {filledCount}/6</p>
      <p>turn: {turnCount}</p>
      <p>coach_level: {coachLevel ?? '—'}</p>
      <p>LLM agent: {(lastAgent as { agent?: string } | undefined)?.agent ?? 'narrative_v2'}</p>
      <p>input_quality: {inputQuality != null ? JSON.stringify(inputQuality) : '—'}</p>
    </div>
  )
}

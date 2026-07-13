import EduGistudyLogo from '../EduGistudyLogo'
import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  questTitle: string
  turnCount: number
  techTransparencyOn: boolean
  onToggleTechTransparency: () => void
}

export default function EduPcTopBar({
  questTitle,
  turnCount,
  techTransparencyOn,
  onToggleTechTransparency,
}: Props) {
  return (
    <header
      className="shrink-0 flex items-center justify-between gap-4 px-5 py-3 border-b z-10"
      style={{ borderColor: eduPc.border, backgroundColor: 'rgba(7,7,7,0.92)' }}
    >
      <EduGistudyLogo size="md" variant="dark" to="/edu" accentColor={eduPc.orange} fontFamily={eduPc.fontLogo} />
      <div className="flex items-center gap-2 min-w-0">
        <span
          className="truncate text-sm font-medium max-w-[280px]"
          style={{ color: eduPc.inkMuted, fontFamily: eduPc.fontBody }}
        >
          {questTitle}
        </span>
        <span
          className="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums"
          style={{
            backgroundColor: eduPc.orangeDim,
            color: eduPc.orange,
            border: `1px solid ${eduPc.border}`,
          }}
        >
          {turnCount}턴
        </span>
        <button
          type="button"
          onClick={onToggleTechTransparency}
          className="shrink-0 w-7 h-7 rounded-md text-xs font-bold border transition-colors hover:border-[#E85D2C]"
          style={{
            borderColor: techTransparencyOn ? eduPc.orange : eduPc.border,
            color: techTransparencyOn ? eduPc.orange : eduPc.inkDim,
            backgroundColor: techTransparencyOn ? eduPc.orangeDim : 'transparent',
          }}
          title="기술 투명 모드 (T)"
          aria-pressed={techTransparencyOn}
        >
          T
        </button>
      </div>
    </header>
  )
}

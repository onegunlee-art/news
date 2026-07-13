import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  label?: string
}

/** LLM 대기 중 스캔바 인디케이터 */
export default function EduPcScanBarIndicator({
  label = '학생 답변 분석 → 되물을 지점 탐색',
}: Props) {
  return (
    <div className="flex flex-col items-center justify-center gap-4 py-12 px-6" role="status">
      <div
        className="w-full max-w-md h-1 rounded-full overflow-hidden"
        style={{ backgroundColor: eduPc.border }}
      >
        <div
          className="h-full w-1/3 rounded-full edu-pc-scan-bar"
          style={{ backgroundColor: eduPc.orange }}
        />
      </div>
      <p
        className="text-sm text-center"
        style={{ color: eduPc.inkMuted, fontFamily: eduPc.fontBody }}
      >
        {label}
      </p>
    </div>
  )
}

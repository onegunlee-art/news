import EduPcScanBarIndicator from './EduPcScanBarIndicator'
import { eduPc } from '../../../constants/eduPcRedesignTheme'

type Props = {
  composeReady: boolean
}

export default function EduPcComposeLoading({ composeReady }: Props) {
  return (
    <div
      className="absolute inset-0 z-20 flex flex-col items-center justify-center"
      style={{ backgroundColor: 'rgba(7,7,7,0.92)' }}
      role="status"
      aria-live="polite"
    >
      <EduPcScanBarIndicator
        label={
          composeReady
            ? '글을 정리하는 중…'
            : '생각판을 읽고 글을 쓰는 중'
        }
      />
      <p className="mt-2 text-xs" style={{ color: eduPc.inkDim }}>
        LLM이 생각판을 바탕으로 글을 생성합니다
      </p>
    </div>
  )
}

import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

type Props = {
  studentAnswer?: string | null
  label?: string
  compact?: boolean
}

const DEFAULT_LABEL = '코치가 읽는 중...'

/** 답 제출 후 — 학생 답 강조 + 코치 읽는 중 (카드·채팅 공용) */
export default function EduCoachWaitingPanel({
  studentAnswer,
  label = DEFAULT_LABEL,
  compact = false,
}: Props) {
  const answerSize = compact ? '1.0625rem' : '1.25rem'
  const labelSize = compact ? eduGame.fontSize.caption : eduGame.fontSize.label

  return (
    <div
      className={`flex flex-col items-center justify-center text-center w-full ${compact ? 'gap-2 py-2' : 'gap-4 py-6'}`}
      aria-live="polite"
      role="status"
      aria-label={label}
    >
      {studentAnswer && (
        <p
          className={`max-w-[90%] font-bold animate-[edu-wait-student-in_0.28s_ease-out] ${eduGameClasses.textKoPre}`}
          style={{
            fontSize: answerSize,
            lineHeight: 1.55,
            color: eduGame.ink,
          }}
        >
          {studentAnswer}
        </p>
      )}
      <p
        className="inline-flex items-center gap-1.5"
        style={{ fontSize: labelSize, color: eduGame.muted }}
      >
        <span>{label}</span>
        <span className="inline-flex items-center gap-0.5 h-3" aria-hidden>
          <span
            className="w-1 h-1 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '0ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1 h-1 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '150ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1 h-1 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '300ms', animationDuration: '0.9s' }}
          />
        </span>
      </p>
    </div>
  )
}

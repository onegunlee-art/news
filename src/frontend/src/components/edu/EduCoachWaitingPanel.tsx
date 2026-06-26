import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import TypingIndicator from './TypingIndicator'

type Props = {
  studentAnswer?: string | null
  label?: string
  compact?: boolean
}

/** 답 제출 후 — 입력 불가 + 생각 중 (카드·채팅 공용) */
export default function EduCoachWaitingPanel({
  studentAnswer,
  label,
  compact = false,
}: Props) {
  return (
    <div className={`flex flex-col ${compact ? 'gap-2' : 'gap-4'} w-full`} aria-live="polite">
      {studentAnswer && (
        <div className="flex justify-end animate-[edu-wait-student-in_0.28s_ease-out]">
          <div
            className={`max-w-[90%] px-4 py-2.5 ${eduGameClasses.studentBubble}`}
            style={{
              backgroundColor: eduGame.bubbleStudent,
              fontSize: eduGame.fontSize.body,
              lineHeight: eduGame.lineHeight.body,
            }}
          >
            {studentAnswer}
          </div>
        </div>
      )}
      <div className={`flex ${compact ? 'justify-start' : 'justify-center'} w-full`}>
        <TypingIndicator label={label} />
      </div>
    </div>
  )
}

import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

/** AI 응답 대기 — 생각 중 점 애니메이션 */
export default function TypingIndicator({ label = '생각 중' }: { label?: string }) {
  return (
    <div className="flex justify-start">
      <div
        className={`max-w-[90%] px-4 py-3 rounded-2xl rounded-bl-md border-2 flex items-center gap-2.5 ${eduGameClasses.textKo}`}
        style={{
          backgroundColor: eduGame.bubbleCoach,
          borderColor: eduGame.bubbleCoachBorder,
          color: eduGame.muted,
        }}
        aria-live="polite"
        aria-label={label}
      >
        <span className="inline-flex items-center gap-1 h-4" aria-hidden>
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '0ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '150ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: eduGame.primary, animationDelay: '300ms', animationDuration: '0.9s' }}
          />
        </span>
        <span style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>{label}</span>
      </div>
    </div>
  )
}

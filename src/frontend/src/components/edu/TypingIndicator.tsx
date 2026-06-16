import { EDU_BRAND } from '../../constants/eduBrand'

/** AI 응답 대기 중 표시 (ChatGPT 스타일 점 애니메이션) */
export default function TypingIndicator({ label }: { label?: string }) {
  return (
    <div className="flex justify-start">
      <div
        className="max-w-[85%] px-3 py-2.5 text-sm rounded-2xl rounded-bl-md flex items-center gap-2"
        style={{ backgroundColor: EDU_BRAND.surface, color: EDU_BRAND.muted }}
        aria-live="polite"
        aria-label={label ?? '답변 생성 중'}
      >
        <span className="inline-flex items-center gap-1 h-4">
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: EDU_BRAND.muted, animationDelay: '0ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: EDU_BRAND.muted, animationDelay: '150ms', animationDuration: '0.9s' }}
          />
          <span
            className="w-1.5 h-1.5 rounded-full animate-bounce"
            style={{ backgroundColor: EDU_BRAND.muted, animationDelay: '300ms', animationDuration: '0.9s' }}
          />
        </span>
        {label && <span className="text-xs">{label}</span>}
      </div>
    </div>
  )
}

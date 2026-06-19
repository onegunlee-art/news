import { useState } from 'react'
import type { EduQuestArticle } from '../../services/eduApi'

const ROLE_LABEL: Record<string, string> = {
  primary: '핵심',
  context: '배경',
  counter: '다른 시각',
}

type Props = {
  article: EduQuestArticle
  disabled?: boolean
  onInteract?: () => void
}

export default function EduArticleCard({ article, disabled = false, onInteract }: Props) {
  const [expanded, setExpanded] = useState(false)
  const outlet = article.source_outlet?.trim() || ''
  const perspective = article.media_perspective?.trim() || ''
  const excerptLines = article.excerpt?.split('\n').filter((line) => line.trim() !== '') ?? []

  const handleToggle = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.stopPropagation()
    if (disabled) return
    onInteract?.()
    setExpanded((v) => !v)
  }

  /** Desktop only: blur evidence textarea before click focus jump (0f58f15). Touch must not use pointerdown-only blur — iOS loses the click. */
  const handlePointerDown = (e: React.PointerEvent<HTMLButtonElement>) => {
    if (e.pointerType !== 'mouse') return
    e.stopPropagation()
    if (disabled) return
    onInteract?.()
  }

  return (
    <div className={`border border-[#ccc] rounded overflow-hidden bg-white ${disabled ? 'opacity-60 pointer-events-none' : ''}`}>
      <button
        type="button"
        onClick={handleToggle}
        onPointerDown={handlePointerDown}
        disabled={disabled}
        className="w-full text-left p-2 hover:bg-[#f5f5f5] transition-colors disabled:hover:bg-white"
        aria-expanded={expanded}
      >
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 mb-1 flex-wrap">
              <span className="text-[10px] font-bold border border-[#1a1a1a] px-1 py-0.5 shrink-0">
                {ROLE_LABEL[article.role] ?? article.role}
              </span>
              {outlet && (
                <span className="text-[10px] text-[#666] truncate">{outlet}</span>
              )}
            </div>
            <p className="text-xs font-medium leading-snug">{article.title}</p>
            {perspective && (
              <p className="text-[10px] text-[#555] mt-1 leading-snug">{perspective}</p>
            )}
          </div>
          <span className="text-[10px] text-[#888] shrink-0 pt-0.5">
            {expanded ? '접기 ▲' : '펼치기 ▼'}
          </span>
        </div>
      </button>
      {expanded && excerptLines.length > 0 && (
        <div className="px-2 pb-2 border-t border-[#eee] space-y-1">
          {excerptLines.map((line, i) => (
            <p key={i} className="text-[10px] text-[#777] leading-relaxed">
              {line}
            </p>
          ))}
        </div>
      )}
    </div>
  )
}

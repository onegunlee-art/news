import { useState, useRef, useEffect } from 'react'

export interface ShareMenuProps {
  title: string
  description?: string
  imageUrl?: string
  webUrl: string
  /** 버튼 스타일용 클래스 (아이콘 색/호버 등) */
  className?: string
  /** 버튼 제목 (접근성) */
  titleAttr?: string
}

/** 공유하기 버튼: 클릭 시 링크 복사 / (지원 시) 시스템 공유 메뉴 */
export default function ShareMenu({ title, description = '', webUrl, className = '', titleAttr = '공유하기' }: ShareMenuProps) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const handleClickOutside = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [open])

  const handleCopyLink = async () => {
    setOpen(false)
    try {
      await navigator.clipboard.writeText(webUrl)
      alert('링크가 복사되었습니다.')
    } catch {
      alert('링크 복사에 실패했습니다.')
    }
  }

  const handleNativeShare = async () => {
    setOpen(false)
    if (!navigator.share) {
      handleCopyLink()
      return
    }
    try {
      await navigator.share({
        title,
        text: description || title,
        url: webUrl,
      })
    } catch (err: unknown) {
      if ((err as Error).name !== 'AbortError') {
        handleCopyLink()
      }
    }
  }

  return (
    <div className="relative inline-block" ref={menuRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={`p-1 transition-colors text-gray-400 hover:text-gray-600 ${className}`}
        title={titleAttr}
        aria-label={titleAttr}
        aria-expanded={open}
        aria-haspopup="true"
      >
        {/* 공유 아이콘 (상자 + 위로 화살표) - 다른 아이콘과 밀도 통일 */}
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
      </button>

      {open && (
        <div
          className="absolute right-0 top-full mt-1 py-1 min-w-[160px] bg-white rounded-lg shadow-lg border border-gray-200 z-50"
          role="menu"
        >
          <button
            type="button"
            role="menuitem"
            onClick={handleCopyLink}
            className="w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2"
          >
            <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-3M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            링크 복사
          </button>
          {typeof navigator !== 'undefined' && 'share' in navigator && typeof navigator.share === 'function' && (
            <button
              type="button"
              role="menuitem"
              onClick={handleNativeShare}
              className="w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2"
            >
              <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              더 많은 앱으로 공유
            </button>
          )}
        </div>
      )}
    </div>
  )
}

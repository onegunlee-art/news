import { useState, useRef, useEffect } from 'react'
import MaterialIcon from './MaterialIcon'

export interface ShareMenuProps {
  title: string
  description?: string
  imageUrl?: string
  webUrl: string
  /** 버튼 스타일용 클래스 (아이콘 색/호버 등) */
  className?: string
  /** 버튼 제목 (접근성) */
  titleAttr?: string
  /** 아이콘 크기 클래스 (메인에서 Play/Bookmark와 동일하게 할 때 사용, 예: w-5 h-5) */
  iconClassName?: string
}

/** 공유하기 버튼: 클릭 시 링크 복사 / (지원 시) 시스템 공유 메뉴 */
const DEFAULT_ICON_CLASS = 'w-5 h-5'

export default function ShareMenu({ title, description = '', webUrl, className = '', titleAttr = '공유하기', iconClassName = DEFAULT_ICON_CLASS }: ShareMenuProps) {
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
        className={`p-1 transition-colors ${className || 'text-gray-400 hover:text-gray-600'}`}
        title={titleAttr}
        aria-label={titleAttr}
        aria-expanded={open}
        aria-haspopup="true"
      >
        <MaterialIcon name="ios_share" className={iconClassName} size={iconClassName.includes('w-5') ? 20 : 16} />
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
            <MaterialIcon name="link" className="w-4 h-4 text-gray-500" size={16} />
            링크 복사
          </button>
          {typeof navigator !== 'undefined' && 'share' in navigator && typeof navigator.share === 'function' && (
            <button
              type="button"
              role="menuitem"
              onClick={handleNativeShare}
              className="w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2"
            >
              <MaterialIcon name="ios_share" className="w-4 h-4 text-gray-500" size={16} />
              공유하기
            </button>
          )}
        </div>
      )}
    </div>
  )
}

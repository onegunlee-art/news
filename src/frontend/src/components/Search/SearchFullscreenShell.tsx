import { useEffect, type ReactNode } from 'react'
import MaterialIcon from '../Common/MaterialIcon'
import { useBodyScrollLock } from '../../hooks/useBodyScrollLock'

type SearchFullscreenShellProps = {
  children: ReactNode
  onClose?: () => void
  showBackButton?: boolean
  className?: string
}

export default function SearchFullscreenShell({
  children,
  onClose,
  showBackButton = Boolean(onClose),
  className = '',
}: SearchFullscreenShellProps) {
  useBodyScrollLock(true)

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [])

  return (
    <div
      className={`fixed inset-0 z-[100] flex flex-col overflow-hidden overscroll-none touch-none bg-page h-[100dvh] max-h-[100dvh] min-h-[100dvh] ${className}`.trim()}
      style={{
        height: '100dvh',
        maxHeight: '100dvh',
        minHeight: '100dvh',
        ...(typeof CSS !== 'undefined' && CSS.supports('height', '100svh')
          ? { height: '100svh', maxHeight: '100svh', minHeight: '100svh' }
          : {}),
      }}
      role="dialog"
      aria-modal="true"
      aria-label="gister 검색"
    >
      {showBackButton && onClose ? (
        <div className="shrink-0 flex items-center px-2 pt-[max(0.75rem,env(safe-area-inset-top))] pl-[max(0.5rem,env(safe-area-inset-left))] pr-[max(0.5rem,env(safe-area-inset-right))]">
          <button
            type="button"
            onClick={onClose}
            className="shrink-0 rounded-lg p-2.5 text-page hover:bg-page-secondary/80 transition-colors"
            aria-label="검색 닫기"
          >
            <MaterialIcon name="arrow_back" className="w-6 h-6 text-page" size={24} />
          </button>
        </div>
      ) : null}
      <div className="flex flex-1 min-h-0 flex-col overflow-hidden">
        {children}
      </div>
    </div>
  )
}

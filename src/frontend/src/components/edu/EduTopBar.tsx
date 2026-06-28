import { useState } from 'react'
import { Link } from 'react-router-dom'
import EduGistudyLogo from './EduGistudyLogo'
import EduGamingStreakFlame from './EduGamingStreakFlame'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

export type EduTopBarMenuItem = {
  label: string
  to?: string
  onClick?: () => void
  accent?: boolean
}

type Props = {
  streakDays?: number
  variant?: 'light' | 'dark'
  menuItems: EduTopBarMenuItem[]
  className?: string
}

/** EDU 상단바 — 로고(좌) · 불꽃 스트릭 + 햄버거(우) */
export default function EduTopBar({
  streakDays = 0,
  variant = 'light',
  menuItems,
  className = '',
}: Props) {
  const [open, setOpen] = useState(false)
  const bg = variant === 'dark' ? '#0D0D0D' : eduGame.bg
  const border = variant === 'dark' ? '#333333' : eduGame.border
  const iconColor = variant === 'dark' ? '#ffffff' : eduGame.ink

  const close = () => setOpen(false)

  return (
    <>
      <header
        className={`sticky top-0 z-30 border-b px-4 ${className}`}
        style={{
          borderColor: border,
          backgroundColor: bg,
          paddingTop: 'max(0.625rem, env(safe-area-inset-top))',
        }}
      >
        <div className="max-w-lg mx-auto flex items-center justify-between gap-3 h-11">
          <EduGistudyLogo size="md" variant={variant} />

          <div className="flex items-center gap-2 shrink-0">
            {streakDays > 0 && (
              <EduGamingStreakFlame streakDays={streakDays} variant="compact" />
            )}
            <button
              type="button"
              onClick={() => setOpen((v) => !v)}
              className="p-2 -mr-2 rounded-lg touch-manipulation"
              aria-label="메뉴"
              aria-expanded={open}
            >
              <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden>
                <path
                  d="M4 7h16M4 12h16M4 17h16"
                  stroke={iconColor}
                  strokeWidth="2"
                  strokeLinecap="round"
                />
              </svg>
            </button>
          </div>
        </div>
      </header>

      {open && (
        <>
          <button
            type="button"
            className="fixed inset-0 z-40 bg-black/30"
            aria-label="메뉴 닫기"
            onClick={close}
          />
          <nav
            className={`fixed top-0 right-0 z-50 h-full w-[min(100%,280px)] shadow-xl border-l ${eduGameClasses.textKo}`}
            style={{
              backgroundColor: eduGame.bg,
              borderColor: eduGame.border,
              paddingTop: 'max(1rem, env(safe-area-inset-top))',
            }}
            aria-label="EDU 메뉴"
          >
            <div className="flex items-center justify-between px-4 pb-4 border-b" style={{ borderColor: eduGame.border }}>
              <EduGistudyLogo size="sm" to={undefined} />
              <button
                type="button"
                onClick={close}
                className="p-2 text-2xl leading-none"
                style={{ color: eduGame.muted }}
                aria-label="닫기"
              >
                ×
              </button>
            </div>
            <ul className="py-2">
              {menuItems.map((item) => (
                <li key={item.label}>
                  {item.to ? (
                    <Link
                      to={item.to}
                      onClick={close}
                      className="block px-5 py-3.5 font-medium no-underline touch-manipulation"
                      style={{
                        color: item.accent ? eduGame.primary : eduGame.ink,
                        fontSize: eduGame.fontSize.body,
                      }}
                    >
                      {item.label}
                    </Link>
                  ) : (
                    <button
                      type="button"
                      onClick={() => {
                        item.onClick?.()
                        close()
                      }}
                      className="w-full text-left px-5 py-3.5 font-medium touch-manipulation"
                      style={{
                        color: item.accent ? eduGame.primary : eduGame.ink,
                        fontSize: eduGame.fontSize.body,
                      }}
                    >
                      {item.label}
                    </button>
                  )}
                </li>
              ))}
            </ul>
          </nav>
        </>
      )}
    </>
  )
}

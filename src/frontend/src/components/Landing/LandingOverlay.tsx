import { useState, useCallback, useEffect } from 'react'
import { AnimatePresence, motion } from 'framer-motion'

const STORAGE_KEY = 'landing_seen'

export default function LandingOverlay() {
  const [visible, setVisible] = useState(() => {
    try {
      return !localStorage.getItem(STORAGE_KEY)
    } catch {
      return false
    }
  })

  const dismiss = useCallback(() => {
    setVisible(false)
    try {
      localStorage.setItem(STORAGE_KEY, '1')
    } catch { /* ignore */ }
  }, [])

  useEffect(() => {
    if (!visible) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') dismiss()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [visible, dismiss])

  /** 배경(홈 피드) 스크롤 방지 — 모바일 Safari 등에서도 뒤 페이지가 밀리지 않도록 */
  useEffect(() => {
    if (!visible) return

    const html = document.documentElement
    const body = document.body
    const scrollY = window.scrollY

    const prevHtmlOverflow = html.style.overflow
    const prevHtmlOverscroll = html.style.overscrollBehavior
    const prevBodyOverflow = body.style.overflow
    const prevBodyOverscroll = body.style.overscrollBehavior
    const prevBodyPosition = body.style.position
    const prevBodyTop = body.style.top
    const prevBodyLeft = body.style.left
    const prevBodyRight = body.style.right
    const prevBodyWidth = body.style.width

    html.style.overflow = 'hidden'
    html.style.overscrollBehavior = 'none'
    body.style.overflow = 'hidden'
    body.style.overscrollBehavior = 'none'
    body.style.position = 'fixed'
    body.style.top = `-${scrollY}px`
    body.style.left = '0'
    body.style.right = '0'
    body.style.width = '100%'

    return () => {
      html.style.overflow = prevHtmlOverflow
      html.style.overscrollBehavior = prevHtmlOverscroll
      body.style.overflow = prevBodyOverflow
      body.style.overscrollBehavior = prevBodyOverscroll
      body.style.position = prevBodyPosition
      body.style.top = prevBodyTop
      body.style.left = prevBodyLeft
      body.style.right = prevBodyRight
      body.style.width = prevBodyWidth
      window.scrollTo(0, scrollY)
    }
  }, [visible])

  return (
    <AnimatePresence>
      {visible && (
        <motion.div
          key="landing"
          initial={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.5, ease: 'easeOut' }}
          onClick={dismiss}
          role="button"
          tabIndex={0}
          aria-label="탭하여 시작"
          className="fixed inset-0 z-[100] cursor-pointer select-none overflow-hidden touch-none overscroll-none"
          style={{
            background: `conic-gradient(
              from 105.22deg at 146.27% 57.89%,
              #0b0b0b -146.7deg,
              #000000 99.25deg,
              #545454 164.79deg,
              #0b0b0b 213.3deg,
              #000000 459.25deg
            )`,
          }}
        >
          {/* 로고 + 태그라인: 절대 좌표 분리 시 PC에서 줄바꿈 시 겹침 → flex 스택 + gap */}
          <div
            className="absolute z-[1] flex flex-col gap-5 md:gap-10"
            style={{
              top: 'clamp(3rem, 16dvh, 9rem)',
              left: '10%',
              maxWidth: 'min(28rem, calc(100vw - 20%))',
            }}
          >
            <h1
              className="m-0 text-white shrink-0"
              style={{
                fontFamily: '"Lobster", cursive',
                fontSize: 'clamp(54px, 9vw, 84px)',
                lineHeight: 1.15,
                marginTop: '-clamp(0.35rem, 1.2dvh, 0.85rem)',
              }}
            >
              the gist.
            </h1>
            <div
              className="flex flex-col gap-1 text-white md:max-w-xl"
              role="group"
              aria-label="The Economist / Foreign Affairs / Financial Times etc. 유명 글로벌 저널 AI 분석"
              style={{
                fontFamily: '"Noto Sans KR", sans-serif',
                fontWeight: 300,
                fontSize: 'clamp(14.4px, 2.7vw, 19.2px)',
                lineHeight: 1.65,
                letterSpacing: '-0.05em',
              }}
            >
              <p className="m-0">
                The Economist / Foreign Affairs / Financial Times etc.
              </p>
              <p className="m-0">유명 글로벌 저널 AI 분석</p>
            </div>
          </div>

          {/* 추상 스피로그래프 — Figma: 704x678, left:-317, top:373 → 왼쪽 하단 */}
          <img
            src="/img/landing-spiral.svg"
            alt=""
            aria-hidden="true"
            className="absolute pointer-events-none"
            style={{
              width: 'clamp(400px, 85vw, 720px)',
              height: 'auto',
              left: 'clamp(-45%, -35vw, -20%)',
              bottom: 'clamp(-15%, -10dvh, -5%)',
              opacity: 0.55,
            }}
          />

          {/* 하단 힌트 */}
          <span
            className="absolute left-1/2 -translate-x-1/2 text-white/50 animate-pulse"
            style={{
              bottom: 'clamp(40px, 8dvh, 80px)',
              fontFamily: '"Noto Sans KR", sans-serif',
              fontSize: '13px',
            }}
          >
            탭하여 시작
          </span>
        </motion.div>
      )}
    </AnimatePresence>
  )
}

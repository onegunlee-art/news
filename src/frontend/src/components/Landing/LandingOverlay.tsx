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
          className="fixed inset-0 z-[100] cursor-pointer select-none overflow-hidden"
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
                fontSize: 'clamp(36px, 6vw, 56px)',
                lineHeight: 1.15,
              }}
            >
              the gist.
            </h1>
            <p
              className="m-0 text-white md:max-w-xl"
              style={{
                fontFamily: '"Noto Sans KR", sans-serif',
                fontWeight: 300,
                fontSize: 'clamp(12px, 2.25vw, 16px)',
                lineHeight: 1.65,
                letterSpacing: '-0.05em',
              }}
            >
              The Economist / Foreign Affairs / Financial Times etc. 유명 글로벌 저널 AI 분석
            </p>
          </div>

          {/* 추상 나선 SVG */}
          <svg
            viewBox="0 0 704 678"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className="absolute pointer-events-none"
            style={{
              width: 'max(65vw, 400px)',
              height: 'auto',
              right: '-15%',
              bottom: '-10%',
              opacity: 0.35,
            }}
          >
            <path
              d="M352 40 C540 40 650 190 650 350 C650 550 430 640 352 660 C274 640 54 550 54 350 C54 190 164 40 352 40 Z"
              fill="none" stroke="#fff" strokeWidth="0.5"
              style={{ vectorEffect: 'non-scaling-stroke' }}
            />
            <path
              d="M352 100 C490 100 580 225 580 345 C580 490 410 560 352 578 C294 560 124 490 124 345 C124 225 214 100 352 100 Z"
              fill="none" stroke="#fff" strokeWidth="0.5" opacity="0.8"
              style={{ vectorEffect: 'non-scaling-stroke' }}
            />
            <path
              d="M352 160 C445 160 515 252 515 340 C515 440 395 500 352 514 C309 500 189 440 189 340 C189 252 259 160 352 160 Z"
              fill="none" stroke="#fff" strokeWidth="0.5" opacity="0.6"
              style={{ vectorEffect: 'non-scaling-stroke' }}
            />
            <path
              d="M352 220 C405 220 450 275 450 335 C450 395 385 440 352 452 C319 440 254 395 254 335 C254 275 299 220 352 220 Z"
              fill="none" stroke="#fff" strokeWidth="0.5" opacity="0.4"
              style={{ vectorEffect: 'non-scaling-stroke' }}
            />
            <path
              d="M352 270 C380 270 400 300 400 330 C400 362 372 385 352 392 C332 385 304 362 304 330 C304 300 324 270 352 270 Z"
              fill="none" stroke="#fff" strokeWidth="0.5" opacity="0.25"
              style={{ vectorEffect: 'non-scaling-stroke' }}
            />
          </svg>

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

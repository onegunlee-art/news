import { useCallback, useRef, useState } from 'react'
import type { CSSProperties } from 'react'

interface LandingPageProps {
  onEnter: () => void
}

/** Figma: conic gold/orange + 다크·블루·그린 변주 */
const BACKGROUNDS = [
  'conic-gradient(from 105.22deg at 146.27% 57.89%, #FFE563 -122.88deg, #D94800 99.25deg, #B47500 164.79deg, #FFE563 237.12deg, #D94800 459.25deg)',
  'conic-gradient(from 105deg at 130% 55%, #4a4a4a -100deg, #0f0f0f 80deg, #2d2d2d 200deg, #4a4a4a 260deg)',
  'conic-gradient(from 105deg at 130% 55%, #9fd4ff -100deg, #1a4a8a 80deg, #3d7dcc 200deg, #9fd4ff 260deg)',
  'conic-gradient(from 105deg at 130% 55%, #c8f090 -100deg, #1e5a18 80deg, #5aaf3e 200deg, #c8f090 260deg)',
] as const

const BARCODE_LABELS = [
  'The Economist',
  'Foreign Affairs',
  'Financial Times',
  'and UN Meetings',
] as const

/** Figma: Libre Barcode 128 Text 48px / line-height 35px / right / -0.05em */
const barcodeBlockStyle: CSSProperties = {
  fontFamily: "'Libre Barcode 128 Text', system-ui, sans-serif",
  fontWeight: 400,
  fontSize: 'clamp(1.5rem, 11vw, 3rem)',
  lineHeight: 'clamp(28px, 9vw, 40px)',
  textAlign: 'right',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
}

/** Figma: Noto Sans KR 300, 17px, line-height 28px, -0.05em */
const bodyBlockStyle: CSSProperties = {
  fontFamily: "'Noto Sans KR', sans-serif",
  fontWeight: 300,
  fontSize: '17px',
  lineHeight: '28px',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
  maxWidth: '203px',
}

export default function LandingPage({ onEnter }: LandingPageProps) {
  const [index] = useState(() => Math.floor(Math.random() * BACKGROUNDS.length))
  const enteredRef = useRef(false)

  const handleEnter = useCallback(() => {
    if (enteredRef.current) return
    enteredRef.current = true
    onEnter()
  }, [onEnter])

  return (
    <div
      className="fixed inset-0 z-[9999] flex cursor-pointer items-stretch justify-center touch-manipulation"
      role="button"
      tabIndex={0}
      aria-label="화면을 눌러 계속하기"
      onClick={handleEnter}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          handleEnter()
        }
      }}
    >
      <div
        className="pointer-events-none mx-auto flex h-full min-h-[100dvh] w-full max-w-[402px] flex-col px-8 py-12 md:px-12 md:py-16"
        style={{
          background: BACKGROUNDS[index],
        }}
      >
        {/* Figma: Lobster 36px, line-height 45px */}
        <header className="shrink-0">
          <h1
            className="m-0 text-white"
            style={{
              fontFamily: "'Lobster', cursive",
              fontWeight: 400,
              fontSize: 'clamp(28px, 9vw, 36px)',
              lineHeight: '45px',
            }}
          >
            the gist.
          </h1>
        </header>

        <div className="flex min-h-0 flex-1 flex-col justify-center py-8">
          <div style={bodyBlockStyle}>
            <div className="flex flex-col">
              <span>+ 이코노미스트</span>
              <span>+ 포린 어페어즈</span>
              <span>+ 파이낸셜 타임즈</span>
            </div>
            <p className="m-0 mt-4">
              유명 저널 <strong className="font-semibold">AI 분석으로</strong>
            </p>
            <p className="m-0 font-semibold">글로벌 이슈 심플하게 따라잡기</p>
          </div>
        </div>

        {/* Figma: 바코드 블록 width 317px, text-align right — 폰트가 바코드+라벨 통합 */}
        <div
          className="mt-auto flex w-full max-w-[317px] shrink-0 flex-col gap-3 self-end"
          aria-hidden
        >
          {BARCODE_LABELS.map((label) => (
            <div key={label} style={barcodeBlockStyle}>
              {label}
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

import { useCallback, useRef } from 'react'
import type { CSSProperties } from 'react'

interface LandingPageProps {
  onEnter: () => void
}

/** 다크 톤 conic 그라데이션 (단색 검정 대신 입체감) */
const DARK_CONIC_BACKGROUND =
  'conic-gradient(from 105deg at 130% 55%, #4a4a4a -100deg, #0f0f0f 80deg, #2d2d2d 200deg, #4a4a4a 260deg)'

const BARCODE_LABELS = [
  'The Economist',
  'Foreign Affairs',
  'Financial Times',
  'and UN Meetings',
] as const

/** 영어 소스 목록: 라틴은 Noto Sans (본문 KR과 동일 계열) */
const sourceListStyle: CSSProperties = {
  fontFamily: "'Noto Sans', 'Noto Sans KR', sans-serif",
  fontWeight: 300,
  fontSize: '17px',
  lineHeight: '24px',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
}

const SOURCE_LINES = [
  '+ The Economist',
  '+ Foreign Affairs',
  '+ Financial Times',
] as const

const barcodeBlockStyle: CSSProperties = {
  fontFamily: "'Libre Barcode 128 Text', system-ui, sans-serif",
  fontWeight: 400,
  fontSize: 'clamp(1.5rem, 11vw, 3rem)',
  lineHeight: 'clamp(28px, 9vw, 40px)',
  textAlign: 'right',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
}

/** 본문: 둘째 태그라인이 한 줄에 들어가도록 maxWidth 여유 */
const bodyBlockStyle: CSSProperties = {
  fontFamily: "'Noto Sans KR', sans-serif",
  fontWeight: 300,
  fontSize: '17px',
  lineHeight: '28px',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
  maxWidth: 'min(280px, calc(100vw - 4rem))',
}

export default function LandingPage({ onEnter }: LandingPageProps) {
  const enteredRef = useRef(false)

  const handleEnter = useCallback(() => {
    if (enteredRef.current) return
    enteredRef.current = true
    onEnter()
  }, [onEnter])

  return (
    <div
      className="fixed inset-0 z-[9999] flex cursor-pointer items-stretch justify-center touch-manipulation outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-white/25"
      style={{ background: DARK_CONIC_BACKGROUND }}
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
      <div className="pointer-events-none mx-auto flex h-full min-h-[100dvh] w-full max-w-[402px] flex-col bg-transparent px-8 py-12 md:px-12 md:py-16">
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
            <div className="flex flex-col gap-0.5" style={sourceListStyle}>
              {SOURCE_LINES.map((line) => (
                <span key={line}>{line}</span>
              ))}
            </div>
            <p className="m-0 mt-4">
              <strong className="font-semibold">유명 저널 AI 분석</strong>으로
            </p>
            <p className="m-0 whitespace-nowrap">
              글로벌 이슈 심플하게 따라잡기
            </p>
          </div>
        </div>

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

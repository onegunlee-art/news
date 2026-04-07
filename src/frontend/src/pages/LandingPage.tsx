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

/** + 리스트: 본문 대비 2배 타이포 (기존 17px → 34px) */
const sourceListStyle: CSSProperties = {
  fontFamily: "'Noto Sans', 'Noto Sans KR', sans-serif",
  fontWeight: 300,
  fontSize: '34px',
  lineHeight: '48px',
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

/** 한글 본문: 2배 타이포 */
const bodyBlockStyle: CSSProperties = {
  fontFamily: "'Noto Sans KR', sans-serif",
  fontWeight: 300,
  fontSize: '34px',
  lineHeight: '56px',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
  maxWidth: 'min(560px, calc(100vw - 4rem))',
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
      <div className="pointer-events-none mx-auto flex h-full min-h-[100dvh] w-full max-w-[402px] flex-col bg-transparent px-8 py-12 md:max-w-5xl md:px-12 md:py-16">
        {/* 1레이어: 로고 */}
        <header className="w-full shrink-0 text-center">
          <h1 className="m-0 font-normal text-white [font-family:'Lobster',cursive] text-[clamp(28px,9vw,36px)] leading-[45px] md:text-[clamp(56px,6vw,72px)] md:leading-[90px]">
            the gist.
          </h1>
        </header>

        {/* 2·3레이어: 가운데 영역 = 글(위) / 바코드(하단 고정) */}
        <div className="flex min-h-0 flex-1 flex-col py-8">
          <div className="flex min-h-0 flex-1 flex-col justify-center">
            <div style={bodyBlockStyle}>
              <div className="flex flex-col gap-1" style={sourceListStyle}>
                {SOURCE_LINES.map((line) => (
                  <span key={line}>{line}</span>
                ))}
              </div>
              <p className="m-0 mt-6">
                <strong className="font-semibold">유명 저널 AI 분석</strong>으로
              </p>
              <p className="m-0 max-w-full break-keep-ko-mobile">
                글로벌 이슈 심플하게 따라잡기
              </p>
            </div>
          </div>

          <div
            className="mt-8 flex w-full max-w-[317px] shrink-0 flex-col gap-3 self-end md:max-w-md md:gap-4"
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
    </div>
  )
}

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

/** 모바일; md 이상은 Tailwind로 1.5배 */
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

/** 모바일; PC는 클래스로 크기 업 */
const barcodeBlockStyle: CSSProperties = {
  fontFamily: "'Libre Barcode 128 Text', system-ui, sans-serif",
  fontWeight: 400,
  fontSize: 'clamp(1.5rem, 11vw, 3rem)',
  lineHeight: 'clamp(28px, 9vw, 40px)',
  textAlign: 'right',
  letterSpacing: '-0.05em',
  color: '#FFFFFF',
}

/** 모바일 본문; md 이상은 Tailwind */
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
      <div className="pointer-events-none mx-auto flex h-full min-h-[100dvh] w-full max-w-[402px] flex-col bg-transparent px-8 py-12 md:max-w-5xl md:px-12 md:py-16">
        <header className="w-full shrink-0 text-center">
          <h1 className="m-0 font-normal text-white [font-family:'Lobster',cursive] text-[clamp(28px,9vw,36px)] leading-[45px] md:text-[clamp(56px,6vw,72px)] md:leading-[90px]">
            the gist.
          </h1>
        </header>

        <div className="flex min-h-0 flex-1 flex-col py-8 md:flex-row md:items-end md:justify-between md:gap-12 lg:gap-20">
          <div className="flex min-h-0 flex-1 flex-col justify-center md:flex-none md:justify-end">
            <div
              className="md:max-w-[min(420px,calc(100%-1rem))] md:text-[25.5px] md:leading-[42px] md:tracking-[-0.05em]"
              style={bodyBlockStyle}
            >
              <div
                className="flex flex-col gap-0.5 md:text-[25.5px] md:leading-9 md:tracking-[-0.05em]"
                style={sourceListStyle}
              >
                {SOURCE_LINES.map((line) => (
                  <span key={line}>{line}</span>
                ))}
              </div>
              <p className="m-0 mt-4">
                <strong className="font-semibold">유명 저널 AI 분석</strong>으로
              </p>
              <p className="m-0 whitespace-nowrap md:whitespace-normal">
                글로벌 이슈 심플하게 따라잡기
              </p>
            </div>
          </div>

          <div
            className="mt-auto flex w-full max-w-[317px] shrink-0 flex-col gap-3 self-end md:mt-0 md:max-w-none md:flex-initial md:gap-4"
            aria-hidden
          >
            {BARCODE_LABELS.map((label) => (
              <div
                key={label}
                className="md:[font-size:clamp(2.25rem,4.2vw,4rem)] md:[line-height:clamp(40px,5.5vw,52px)]"
                style={barcodeBlockStyle}
              >
                {label}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

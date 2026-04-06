import { useCallback, useLayoutEffect, useRef, useState } from 'react'
import type { CSSProperties } from 'react'

interface LandingPageProps {
  onEnter: () => void
}

/** 0: 골드→버nt 오렌지, 1~3: 다크·블루·그린 */
const GRADIENTS = [
  'linear-gradient(180deg, #E8C84A 0%, #D4AF37 28%, #C4621A 72%, #B84312 100%)',
  'linear-gradient(180deg, #2A2A2A 0%, #1A1A1A 100%)',
  'linear-gradient(180deg, #4A9FE8 0%, #2E6CB5 55%, #1E4A8A 100%)',
  'linear-gradient(180deg, #6BC94E 0%, #4A9E38 50%, #2F6E28 100%)',
] as const

const BARCODE_TRACK_CLASS = 'w-[min(160px,85vw)]'

/** 막대·간격 교차 시퀀스(짝수 인덱스 = 흰 막대). 4종 서로 다른 패턴 */
const BARCODE_PATTERNS: readonly (readonly number[])[] = [
  [
    2, 1, 3, 1, 2, 1, 1, 3, 1, 2, 3, 1, 1, 2, 1, 3, 1, 2, 1, 1, 3, 2, 1, 1, 2, 1, 3, 1, 2, 2,
  ],
  [
    3, 2, 1, 2, 1, 3, 2, 1, 1, 2, 3, 1, 2, 1, 1, 2, 2, 1, 3, 1, 1, 2, 1, 3, 2, 1, 2, 1, 3, 1,
  ],
  [
    1, 2, 2, 3, 1, 1, 2, 3, 2, 1, 1, 3, 1, 2, 2, 1, 3, 1, 1, 2, 3, 1, 2, 1, 2, 1, 3, 2, 1, 2,
  ],
  [
    2, 2, 1, 1, 3, 2, 1, 3, 1, 2, 1, 2, 3, 1, 1, 2, 1, 3, 2, 2, 1, 1, 2, 1, 3, 1, 2, 2, 1, 3,
  ],
] as const

const BARCODE_ROWS: readonly { label: string; patternIndex: number }[] = [
  { label: 'The Economist', patternIndex: 0 },
  { label: 'Foreign Affairs', patternIndex: 1 },
  { label: 'Financial Times', patternIndex: 2 },
  { label: 'and UN Meetings', patternIndex: 3 },
]

function Barcode({ pattern }: { pattern: readonly number[] }) {
  let x = 0
  const rects: { x: number; w: number }[] = []
  pattern.forEach((w, i) => {
    if (i % 2 === 0) rects.push({ x, w })
    x += w
  })
  const total = x
  return (
    <svg
      viewBox={`0 0 ${total} 28`}
      className="block h-7 w-full opacity-90"
      preserveAspectRatio="none"
      aria-hidden
    >
      {rects.map((r, i) => (
        <rect key={i} x={r.x} y={0} width={r.w} height={28} fill="white" />
      ))}
    </svg>
  )
}

const CAPTION_FONT =
  'ui-monospace, "Cascadia Mono", "Segoe UI Mono", "Roboto Mono", "Courier New", Courier, monospace'

/** 라벨 시각 폭 = 바코드 폭: 자간 우선, 필요 시 scaleX */
function BarcodeCaption({ text }: { text: string }) {
  const wrapRef = useRef<HTMLDivElement>(null)
  const spanRef = useRef<HTMLSpanElement>(null)
  const [style, setStyle] = useState<CSSProperties>({
    letterSpacing: '0.02em',
    transform: undefined,
    transformOrigin: 'center',
  })

  const fit = () => {
    const wrap = wrapRef.current
    const span = spanRef.current
    if (!wrap || !span) return
    const target = wrap.clientWidth
    if (target < 1) return

    span.style.letterSpacing = '0'
    span.style.transform = 'none'
    const natural = span.scrollWidth

    if (natural > target) {
      const scale = Math.max(0.65, target / natural)
      setStyle({
        letterSpacing: '0',
        transform: `scaleX(${scale})`,
        transformOrigin: 'center',
      })
      return
    }

    let low = -0.04
    let high = 0.55
    for (let i = 0; i < 26; i++) {
      const mid = (low + high) / 2
      span.style.letterSpacing = `${mid}em`
      const w = span.scrollWidth
      if (w < target) low = mid
      else high = mid
    }
    setStyle({
      letterSpacing: `${low}em`,
      transform: undefined,
      transformOrigin: 'center',
    })
  }

  useLayoutEffect(() => {
    fit()
    const wrap = wrapRef.current
    if (!wrap || typeof ResizeObserver === 'undefined') return
    const ro = new ResizeObserver(() => fit())
    ro.observe(wrap)
    return () => ro.disconnect()
  }, [text])

  return (
    <div ref={wrapRef} className="w-full min-w-0">
      <span
        ref={spanRef}
        className="block whitespace-nowrap text-center font-normal leading-tight text-white/95"
        style={{
          fontFamily: CAPTION_FONT,
          fontSize: '10px',
          ...style,
        }}
      >
        {text}
      </span>
    </div>
  )
}

export default function LandingPage({ onEnter }: LandingPageProps) {
  const [index] = useState(() => Math.floor(Math.random() * GRADIENTS.length))
  const enteredRef = useRef(false)

  const handleEnter = useCallback(() => {
    if (enteredRef.current) return
    enteredRef.current = true
    onEnter()
  }, [onEnter])

  return (
    <div
      className="fixed inset-0 z-[9999] flex cursor-pointer items-center justify-center touch-manipulation"
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
        className="pointer-events-none flex h-full w-full flex-col justify-between px-8 py-12 md:px-16 md:py-20"
        style={{
          background: GRADIENTS[index],
          fontFamily: "'Noto Sans KR', sans-serif",
          color: '#ffffff',
        }}
      >
        <div>
          <h1
            className="mb-0 leading-none"
            style={{
              fontFamily: "'Lobster', cursive",
              fontWeight: 400,
              fontSize: 'clamp(2rem, 8vw, 3.5rem)',
            }}
          >
            the gist.
          </h1>
        </div>

        <div className="flex flex-col gap-8 md:gap-12">
          <div
            className="flex flex-col gap-1 leading-relaxed"
            style={{ fontSize: 'clamp(0.85rem, 3.2vw, 1.15rem)' }}
          >
            <span>+ 이코노미스트</span>
            <span>+ 포린 어페어즈</span>
            <span>+ 파이낸셜 타임즈</span>
          </div>

          <div
            className="leading-snug"
            style={{ fontSize: 'clamp(1rem, 4vw, 1.5rem)' }}
          >
            <p className="m-0">
              유명 저널 <strong>AI 분석으로</strong>
            </p>
            <p className="m-0">
              <strong>글로벌 이슈 심플하게 따라잡기</strong>
            </p>
          </div>
        </div>

        <div className={`ml-auto flex flex-col items-end gap-3 ${BARCODE_TRACK_CLASS}`}>
          {BARCODE_ROWS.map(({ label, patternIndex }) => (
            <div key={label} className="flex w-full flex-col gap-0.5">
              <Barcode pattern={BARCODE_PATTERNS[patternIndex]} />
              <BarcodeCaption text={label} />
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

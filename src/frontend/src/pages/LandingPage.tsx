import { useEffect, useLayoutEffect, useRef, useState } from 'react'

interface LandingPageProps {
  onEnter: () => void
}

const GRADIENTS = [
  'linear-gradient(180deg, #D4893C 0%, #B86A1A 100%)',
  'linear-gradient(180deg, #2A2A2A 0%, #1A1A1A 100%)',
  'linear-gradient(180deg, #3A7BD5 0%, #2558A3 100%)',
  'linear-gradient(180deg, #5AAF3E 0%, #3D8A28 100%)',
] as const

const BARCODE_TRACK_CLASS = 'w-[min(160px,85vw)]'

function Barcode() {
  const bars = [
    2, 1, 3, 1, 2, 1, 1, 3, 1, 2, 3, 1, 1, 2, 1, 3, 1, 2, 1, 1, 3, 2, 1, 1,
    2, 1, 3, 1, 2, 1, 1, 2, 3, 1, 1, 2, 1, 3, 1, 2, 1, 1, 2, 1, 3, 2, 1, 1,
  ]
  let x = 0
  const rects: { x: number; w: number }[] = []
  bars.forEach((w, i) => {
    if (i % 2 === 0) rects.push({ x, w })
    x += w
  })
  const total = x
  return (
    <svg
      viewBox={`0 0 ${total} 28`}
      className="block h-7 w-full opacity-80"
      preserveAspectRatio="none"
      aria-hidden
    >
      {rects.map((r, i) => (
        <rect key={i} x={r.x} y={0} width={r.w} height={28} fill="white" />
      ))}
    </svg>
  )
}

/** 라벨 시각 폭을 컨테이너(=바코드 폭)에 맞춤: 자간 우선, 필요 시 scaleX */
function BarcodeCaption({ text }: { text: string }) {
  const wrapRef = useRef<HTMLDivElement>(null)
  const spanRef = useRef<HTMLSpanElement>(null)
  const [style, setStyle] = useState<React.CSSProperties>({
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
      if (w < target * 0.98) low = mid
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
        className="block whitespace-nowrap text-center text-xs opacity-70"
        style={{
          fontFamily: 'monospace, ui-monospace, monospace',
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
  const [visible, setVisible] = useState(true)

  useEffect(() => {
    const fade = setTimeout(() => setVisible(false), 900)
    const go = setTimeout(() => onEnter(), 1200)
    return () => {
      clearTimeout(fade)
      clearTimeout(go)
    }
  }, [onEnter])

  return (
    <div
      className="fixed inset-0 z-[9999] flex items-center justify-center"
      style={{
        opacity: visible ? 1 : 0,
        transition: 'opacity 0.3s ease-out',
      }}
    >
      <div
        className="flex h-full w-full flex-col justify-between px-8 py-12 md:px-16 md:py-20"
        style={{
          background: GRADIENTS[index],
          fontFamily: "'Noto Sans KR', sans-serif",
          color: '#ffffff',
        }}
      >
        {/* 상단: 로고 */}
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

        {/* 중단: 소스 리스트 + 태그라인 */}
        <div className="flex flex-col gap-8 md:gap-12">
          <div
            className="flex flex-col gap-1 leading-relaxed"
            style={{ fontSize: 'clamp(0.85rem, 3.2vw, 1.15rem)' }}
          >
            <span>+이코노미스트</span>
            <span>+포린 어페어즈</span>
            <span>+파이낸셜 타임즈</span>
          </div>

          <div
            className="leading-snug"
            style={{ fontSize: 'clamp(1rem, 4vw, 1.5rem)' }}
          >
            <p className="m-0">
              유명 지널 <strong>AI 분석</strong>으로
            </p>
            <p className="m-0">
              <strong>글로벌 이슈 심플하게 따라잡기</strong>
            </p>
          </div>
        </div>

        {/* 하단: 바코드 + 라벨 — 오른쪽 끝, 라벨 폭 = 바코드 폭 */}
        <div className={`ml-auto flex flex-col items-end gap-3 ${BARCODE_TRACK_CLASS}`}>
          {['The Economist', 'Foreign Affairs', 'Financial Times'].map((name) => (
            <div key={name} className="flex w-full flex-col gap-0.5">
              <Barcode />
              <BarcodeCaption text={name} />
            </div>
          ))}
          <div className="mt-1 w-full">
            <BarcodeCaption text="and UN Meetings" />
          </div>
        </div>
      </div>
    </div>
  )
}

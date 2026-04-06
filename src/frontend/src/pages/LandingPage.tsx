import { useEffect, useState } from 'react'

interface LandingPageProps {
  onEnter: () => void
}

const GRADIENTS = [
  'linear-gradient(180deg, #D4893C 0%, #B86A1A 100%)',
  'linear-gradient(180deg, #2A2A2A 0%, #1A1A1A 100%)',
  'linear-gradient(180deg, #3A7BD5 0%, #2558A3 100%)',
  'linear-gradient(180deg, #5AAF3E 0%, #3D8A28 100%)',
] as const

function Barcode({ width = 140 }: { width?: number }) {
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
      width={width}
      preserveAspectRatio="none"
      className="opacity-80"
    >
      {rects.map((r, i) => (
        <rect key={i} x={r.x} y={0} width={r.w} height={28} fill="white" />
      ))}
    </svg>
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

        {/* 하단: 바코드 장식 */}
        <div className="flex flex-col gap-3">
          {['The Economist', 'Foreign Affairs', 'Financial Times'].map(
            (name) => (
              <div key={name} className="flex flex-col gap-0.5">
                <Barcode width={160} />
                <span
                  className="text-xs tracking-wider opacity-70"
                  style={{ fontFamily: 'monospace' }}
                >
                  {name}
                </span>
              </div>
            ),
          )}
          <span
            className="mt-1 text-xs tracking-wider opacity-70"
            style={{ fontFamily: 'monospace' }}
          >
            and UN Meetings
          </span>
        </div>
      </div>
    </div>
  )
}

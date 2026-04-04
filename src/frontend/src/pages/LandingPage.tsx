import { useState, useEffect, useRef, useLayoutEffect } from 'react'
import GistLogo from '../components/Common/GistLogo'

interface LandingPageProps {
  onEnter: () => void
}

const MEDIA_SOURCES = ['The Economist', 'Foreign Affairs', 'Financial Times', 'UN Meetings'] as const

/** letter-spacing으로 박스 안 정렬감 조절 (동일 박스 크기) */
const BOX_TRACKING: Record<(typeof MEDIA_SOURCES)[number], string> = {
  'The Economist': '0.06em',
  'Foreign Affairs': '0.04em',
  'Financial Times': '0.02em',
  'UN Meetings': '0.12em',
}

const BG_COLORS = ['#2D2D2D', '#3D5A80', '#B07340', '#4A7A5A'] as const

/** 0 차콜·2 오렌지: 흰 글자 / 1 블루·3 그린: 검정 글자 */
function foregroundForIndex(index: number): { main: string; muted: string; border: string } {
  const darkText = index === 1 || index === 3
  if (darkText) {
    return { main: '#000000', muted: 'rgba(0,0,0,0.72)', border: 'rgba(0,0,0,0.45)' }
  }
  return { main: '#ffffff', muted: 'rgba(255,255,255,0.85)', border: 'rgba(255,255,255,0.55)' }
}

function fitLogoFontToWidth(targetWidth: number): number {
  if (targetWidth < 1) return 48
  const el = document.createElement('span')
  el.style.fontFamily = "'Lobster', cursive"
  el.style.fontWeight = '400'
  el.style.position = 'absolute'
  el.style.left = '-9999px'
  el.style.visibility = 'hidden'
  el.style.whiteSpace = 'nowrap'
  el.textContent = 'the gist.'
  document.body.appendChild(el)
  let low = 12
  let high = 320
  for (let i = 0; i < 28; i++) {
    const mid = (low + high) / 2
    el.style.fontSize = `${mid}px`
    const w = el.offsetWidth
    if (w <= targetWidth * 0.98) low = mid
    else high = mid
  }
  document.body.removeChild(el)
  return Math.max(24, Math.floor(low))
}

export default function LandingPage({ onEnter }: LandingPageProps) {
  const [colorIndex, setColorIndex] = useState(0)
  const fitRef = useRef<HTMLDivElement>(null)
  const [logoFontPx, setLogoFontPx] = useState(64)

  const bg = BG_COLORS[colorIndex]
  const fg = foregroundForIndex(colorIndex)
  const arrowCircleDark = colorIndex === 1 || colorIndex === 3

  useEffect(() => {
    const id = setInterval(() => {
      setColorIndex((prev) => (prev + 1) % BG_COLORS.length)
    }, 3000)
    return () => clearInterval(id)
  }, [])

  useLayoutEffect(() => {
    const run = () => {
      const el = fitRef.current
      if (!el) return
      let px = fitLogoFontToWidth(el.clientWidth)
      if (window.matchMedia('(max-width: 767px)').matches) px *= 0.9
      else px *= 0.8
      setLogoFontPx(Math.max(18, Math.floor(px)))
    }
    run()
    const ro = new ResizeObserver(run)
    if (fitRef.current) ro.observe(fitRef.current)
    window.addEventListener('resize', run)
    return () => {
      ro.disconnect()
      window.removeEventListener('resize', run)
    }
  }, [colorIndex])

  return (
    <div
      className="min-h-[100dvh] flex flex-col items-start justify-start px-6 pt-16 pb-28 md:px-16 md:pt-20 md:pb-36 lg:px-24 lg:pt-24"
      style={{
        fontFamily: "'Noto Sans KR', sans-serif",
        backgroundColor: bg,
        transition: 'background-color 1s ease',
        color: fg.main,
      }}
    >
      <div className="w-full max-w-5xl flex flex-col">
        {/* Media boxes — 모바일 약 30% 축소 / PC 3+1 그리드, 글자 1줄 */}
        <div className="flex w-full flex-col gap-1.5 md:grid md:grid-cols-3 md:gap-4">
          {MEDIA_SOURCES.map((name, i) => (
            <div
              key={name}
              role="presentation"
              className={[
                'flex min-w-0 w-full items-center justify-center border-2 font-normal max-md:h-[4.2rem] max-md:px-2 max-md:text-[1.05rem] md:h-28 md:px-2 md:text-xl lg:text-2xl',
                i === 3 ? 'md:col-start-1' : '',
                'whitespace-nowrap md:overflow-hidden md:text-ellipsis',
              ].join(' ')}
              style={{
                borderColor: fg.border,
                color: fg.main,
                letterSpacing: BOX_TRACKING[name],
              }}
            >
              {name}
            </div>
          ))}
        </div>

        <div className="h-24 shrink-0 max-md:h-16 md:h-32 lg:h-36" aria-hidden />

        {/* 모바일: 로고·화살표를 위로(툴바 가림 완화) */}
        <div className="w-full max-md:-translate-y-6 max-md:pb-8">
          <p
            className="w-full pr-[calc(5rem+1rem)] font-light leading-snug tracking-wide max-md:whitespace-nowrap max-md:text-[clamp(0.9375rem,3.8vw,1.125rem)] md:pr-[calc(7rem+1.5rem)] md:text-5xl lg:text-6xl"
            style={{ color: fg.muted }}
          >
            글로벌 이슈,{' '}
            <span className="font-bold" style={{ color: fg.main }}>
              AI로 심플하게
            </span>
          </p>

          <div className="h-8 shrink-0 max-md:h-5 md:h-10" aria-hidden />

          <div className="flex w-full items-center gap-4 max-md:gap-3 md:gap-6">
            <div ref={fitRef} className="min-w-0 flex-1">
              <GistLogo
                size="inline"
                link={false}
                className="block whitespace-nowrap leading-none font-normal !p-0 !text-inherit"
                style={{ fontSize: logoFontPx, color: fg.main }}
              />
            </div>
            <button
              type="button"
              onClick={onEnter}
              className={[
                'flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full border-[3px] md:h-28 md:w-28',
                arrowCircleDark ? 'border-black/50 bg-black' : 'bg-white',
              ].join(' ')}
              style={{ borderColor: arrowCircleDark ? 'rgba(0,0,0,0.35)' : fg.border }}
              aria-label="들어가기"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke={bg}
                strokeWidth={2.5}
                strokeLinecap="round"
                strokeLinejoin="round"
                className="h-10 w-10 md:h-14 md:w-14"
              >
                <path d="M5 12h14" />
                <path d="m12 5 7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

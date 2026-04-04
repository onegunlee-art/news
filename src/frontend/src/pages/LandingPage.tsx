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

function EnterButton({
  onEnter,
  bg,
  arrowCircleDark,
  fg,
  className,
}: {
  onEnter: () => void
  bg: string
  arrowCircleDark: boolean
  fg: { border: string }
  className: string
}) {
  return (
    <button
      type="button"
      onClick={onEnter}
      className={[
        'flex h-[4.5rem] w-[4.5rem] flex-shrink-0 items-center justify-center rounded-full border-[3px] md:h-28 md:w-28',
        arrowCircleDark ? 'border-black/50 bg-black' : 'bg-white',
        className,
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
        className="h-9 w-9 md:h-14 md:w-14"
      >
        <path d="M5 12h14" />
        <path d="m12 5 7 7-7 7" />
      </svg>
    </button>
  )
}

export default function LandingPage({ onEnter }: LandingPageProps) {
  const [colorIndex, setColorIndex] = useState(0)
  const fitRef = useRef<HTMLDivElement>(null)
  const tagRef = useRef<HTMLParagraphElement>(null)
  const [logoFontPx, setLogoFontPx] = useState(64)
  const [tagLetterSpacing, setTagLetterSpacing] = useState('0.02em')

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
      if (window.matchMedia('(max-width: 767px)').matches) px *= 0.9 * 0.8
      else px *= 0.8 * 0.85
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

  /** 모바일: 태그라인 시각 폭을 로고 폭에 가깝게 — letter-spacing 이진 탐색 */
  useLayoutEffect(() => {
    const run = () => {
      const tag = tagRef.current
      const logoBox = fitRef.current
      if (!tag || !logoBox) return
      if (!window.matchMedia('(max-width: 767px)').matches) {
        tag.style.letterSpacing = ''
        setTagLetterSpacing('0.02em')
        return
      }
      const target = logoBox.scrollWidth
      let low = -0.08
      let high = 0.55
      for (let i = 0; i < 24; i++) {
        const mid = (low + high) / 2
        tag.style.letterSpacing = `${mid}em`
        const w = tag.scrollWidth
        if (w < target * 0.97) low = mid
        else high = mid
      }
      setTagLetterSpacing(`${low}em`)
    }
    let raf = 0
    const schedule = () => {
      cancelAnimationFrame(raf)
      raf = requestAnimationFrame(run)
    }
    schedule()
    window.addEventListener('resize', schedule)
    return () => {
      cancelAnimationFrame(raf)
      window.removeEventListener('resize', schedule)
    }
  }, [logoFontPx, colorIndex])

  const boxClass = [
    'flex min-w-0 w-full items-center justify-center border-2 font-bold max-md:h-[4.2rem] max-md:px-2 max-md:text-[1.05rem] md:h-28 md:px-2 md:text-xl lg:text-2xl',
    'whitespace-nowrap md:overflow-hidden md:text-ellipsis',
  ].join(' ')

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
      <div className="w-full max-w-5xl flex flex-col md:max-w-none md:w-full">
        {/* 박스: 모바일·PC 모두 화살표 열 너비만큼 왼쪽으로 몰기 (3번째 박스 끝 ≈ 화살표 시작) */}
        <div className="w-full max-w-[calc(100%-4.5rem-0.75rem)] md:max-w-[min(66.666667%,calc(100%-7rem-1.5rem))]">
          <div className="flex w-full flex-col gap-1.5 md:grid md:grid-cols-3 md:gap-4">
            {MEDIA_SOURCES.map((name, i) => (
              <div
                key={name}
                role="presentation"
                className={[boxClass, i === 3 ? 'md:col-start-1' : ''].join(' ')}
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
        </div>

        {/* 모바일: 박스↔글자 간격 2배 (기존 max-md:h-16의 2배 → h-32) */}
        <div className="h-24 shrink-0 max-md:h-32 md:h-32 lg:h-36" aria-hidden />

        <div className="relative w-full max-md:-translate-y-6 max-md:pb-8 md:pr-[8rem]">
          <p
            ref={tagRef}
            className="w-full pr-[calc(4.5rem+0.75rem)] font-medium leading-snug max-md:whitespace-nowrap max-md:text-[1.35rem] max-md:leading-tight md:pr-0 md:text-[2.7rem] md:font-light lg:text-[3.375rem]"
            style={{ color: fg.muted, letterSpacing: tagLetterSpacing }}
          >
            글로벌 이슈,{' '}
            <span className="font-bold" style={{ color: fg.main }}>
              AI로 심플하게
            </span>
          </p>

          <div className="h-8 shrink-0 max-md:h-5 md:h-10" aria-hidden />

          <div className="flex w-full items-center gap-4 max-md:gap-3 md:block md:relative">
            <div ref={fitRef} className="min-w-0 flex-1 md:w-full">
              <GistLogo
                size="inline"
                link={false}
                className="block whitespace-nowrap leading-none font-normal !p-0 !text-inherit"
                style={{ fontSize: logoFontPx, color: fg.main }}
              />
            </div>
            <EnterButton
              onEnter={onEnter}
              bg={bg}
              arrowCircleDark={arrowCircleDark}
              fg={fg}
              className="flex-shrink-0 md:hidden"
            />
            <EnterButton
              onEnter={onEnter}
              bg={bg}
              arrowCircleDark={arrowCircleDark}
              fg={fg}
              className="hidden md:flex md:absolute md:right-0 md:top-1/2 md:-translate-y-1/2"
            />
          </div>
        </div>
      </div>
    </div>
  )
}

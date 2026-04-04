import { useState, useEffect } from 'react'
import GistLogo from '../components/Common/GistLogo'

interface LandingPageProps {
  onEnter: () => void
}

const MEDIA_SOURCES = ['The Economist', 'Foreign Affairs', 'Financial Times', 'UN Meetings']

const BG_COLORS = [
  '#2D2D2D',
  '#3D5A80',
  '#B07340',
  '#4A7A5A',
]

export default function LandingPage({ onEnter }: LandingPageProps) {
  const [colorIndex, setColorIndex] = useState(0)

  useEffect(() => {
    const id = setInterval(() => {
      setColorIndex((prev) => (prev + 1) % BG_COLORS.length)
    }, 3000)
    return () => clearInterval(id)
  }, [])

  return (
    <div
      className="min-h-[100dvh] flex flex-col items-start justify-start px-6 pt-16 pb-28 md:px-16 md:pt-20 md:pb-36 lg:px-24 lg:pt-24"
      style={{
        fontFamily: "'Noto Sans KR', sans-serif",
        backgroundColor: BG_COLORS[colorIndex],
        transition: 'background-color 1s ease',
      }}
    >
      <div className="w-full max-w-5xl flex flex-col gap-10 md:gap-14">
        {/* Media source tags */}
        <div className="flex flex-col items-start gap-3 md:flex-row md:flex-wrap md:gap-4">
          {MEDIA_SOURCES.map((name) => (
            <span
              key={name}
              className="inline-block px-5 py-2.5 md:px-7 md:py-3 rounded-full border-2 border-white/50 text-2xl md:text-3xl lg:text-4xl text-white/95 tracking-wide"
            >
              {name}
            </span>
          ))}
        </div>

        {/* Tagline */}
        <p className="text-3xl md:text-5xl lg:text-6xl text-white/80 font-light tracking-wide leading-snug">
          글로벌 이슈, <span className="font-bold text-white">AI로 심플하게</span>
        </p>

        {/* Logo + Enter button */}
        <div className="flex items-center justify-between w-full gap-6">
          <GistLogo
            size="header"
            link={false}
            className="!text-white !text-[3.3rem] md:!text-[5.5rem] lg:!text-[6.5rem]"
          />
          <button
            type="button"
            onClick={onEnter}
            className="flex-shrink-0 w-20 h-20 md:w-28 md:h-28 rounded-full border-[3px] border-white/60 bg-white flex items-center justify-center text-black hover:bg-white/90 transition-colors duration-300"
            aria-label="들어가기"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
              className="w-10 h-10 md:w-14 md:h-14"
            >
              <path d="M5 12h14" />
              <path d="m12 5 7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  )
}

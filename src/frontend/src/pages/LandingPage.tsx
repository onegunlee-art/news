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
    }, 2000)
    return () => clearInterval(id)
  }, [])

  return (
    <div
      className="min-h-screen flex flex-col items-start justify-end px-6 pb-16 md:px-16 md:pb-20 lg:px-24"
      style={{
        fontFamily: "'Noto Sans KR', sans-serif",
        backgroundColor: BG_COLORS[colorIndex],
        transition: 'background-color 1s ease',
      }}
    >
      <div className="w-full max-w-4xl flex flex-col gap-8 md:gap-10">
        {/* Media source tags */}
        <div className="flex flex-col items-start gap-2 md:flex-row md:flex-wrap md:gap-3">
          {MEDIA_SOURCES.map((name) => (
            <span
              key={name}
              className="inline-block px-4 py-1.5 rounded-full border border-white/40 text-xs md:text-sm text-white/90 tracking-wide"
            >
              {name}
            </span>
          ))}
        </div>

        {/* Tagline */}
        <p className="text-base md:text-lg text-white/70 font-light tracking-wide">
          글로벌 이슈, AI로 심플하게
        </p>

        {/* Logo + Enter button */}
        <div className="flex items-center justify-between w-full">
          <GistLogo
            size="header"
            link={false}
            className="!text-white"
          />
          <button
            type="button"
            onClick={onEnter}
            className="flex-shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-full border-2 border-white/50 flex items-center justify-center text-white hover:bg-white/10 transition-colors duration-300"
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
              className="w-5 h-5 md:w-6 md:h-6"
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

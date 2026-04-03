import GistLogo from '../components/Common/GistLogo'

interface LandingPageProps {
  onEnter: () => void
}

const MEDIA_SOURCES = ['Foreign Affairs', 'The Economist', 'Financial Times']

export default function LandingPage({ onEnter }: LandingPageProps) {
  return (
    <div
      className="min-h-screen flex flex-col items-center justify-center bg-white text-black px-6"
      style={{ fontFamily: "'Noto Sans KR', sans-serif" }}
    >
      <div className="flex flex-col items-center gap-10 max-w-md w-full">
        {/* Media sources */}
        <div className="flex flex-col items-center gap-2">
          {MEDIA_SOURCES.map((name) => (
            <span
              key={name}
              className="text-lg md:text-xl tracking-wide font-light text-black/70"
            >
              {name}
            </span>
          ))}
        </div>

        {/* Tagline */}
        <div className="flex flex-col items-center gap-1 text-center">
          <p className="text-sm md:text-base tracking-widest text-black/50 font-normal">
            유명 미디어, 글로벌 개념을 바탕으로
          </p>
          <p className="text-base md:text-lg tracking-wide text-black font-medium">
            AI 분석, 재해석한 콘텐츠
          </p>
        </div>

        {/* Divider */}
        <hr className="w-12 border-t border-black/20" />

        {/* Logo */}
        <GistLogo size="default" link={false} className="!text-black !text-4xl md:!text-5xl" />

        {/* Enter button */}
        <button
          type="button"
          onClick={onEnter}
          className="mt-2 px-10 py-3 border border-black/30 rounded-full text-sm tracking-widest font-normal text-black/80 hover:bg-black hover:text-white transition-colors duration-300"
          style={{ fontFamily: "'Noto Sans KR', sans-serif" }}
        >
          들어가기
        </button>
      </div>
    </div>
  )
}

import { LOBSTER_FONT_FAMILY } from '../../constants/site'

const lobsterStyle = { fontFamily: LOBSTER_FONT_FAMILY, fontWeight: 400 as const }

type SearchGisterTaglineProps = {
  className?: string
}

export default function SearchGisterTagline({ className = '' }: SearchGisterTaglineProps) {
  return (
    <div className={`text-center px-4 ${className}`.trim()}>
      <p className="text-xl md:text-2xl text-page leading-snug tracking-tight font-serif">
        <span className="font-bold text-[1.08em]" style={lobsterStyle}>
          gister
        </span>
        <span>에게 무엇이든 물어보세요</span>
      </p>
      <p className="mt-3 text-base text-page-secondary tracking-wide font-serif">
        AI Agent of the gist.
      </p>
      <p className="hidden md:block mt-3 text-sm text-page-secondary leading-relaxed font-serif">
        의미 기반으로 관련 기사를 찾아 드립니다
      </p>
    </div>
  )
}

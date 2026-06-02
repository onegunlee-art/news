import { LOBSTER_FONT_FAMILY } from '../../constants/site'

type SearchGisterTaglineProps = {
  className?: string
}

export default function SearchGisterTagline({ className = '' }: SearchGisterTaglineProps) {
  return (
    <div className={`text-center px-4 ${className}`.trim()}>
      <p
        className="text-xl md:text-2xl text-page leading-snug tracking-tight"
        style={{ fontFamily: LOBSTER_FONT_FAMILY }}
      >
        <span className="font-bold text-[1.08em]">gister</span>
        <span className="font-normal">에게 무엇이든 물어보세요</span>
      </p>
      <p className="mt-3 text-sm text-page-secondary leading-relaxed">
        의미 기반으로 관련 기사를 찾아 드립니다
      </p>
    </div>
  )
}

import { LOBSTER_FONT_FAMILY } from '../../constants/site'

const lobsterStyle = { fontFamily: LOBSTER_FONT_FAMILY, fontWeight: 400 as const }

type SearchGisterTaglineProps = {
  className?: string
}

export default function SearchGisterTagline({ className = '' }: SearchGisterTaglineProps) {
  return (
    <div className={`text-center px-4 ${className}`.trim()}>
      <p className="text-xl md:text-2xl text-page leading-snug tracking-tight text-center">
        <span className="font-serif">AI 에이전트, </span>
        <span className="font-bold" style={lobsterStyle}>
          gister
        </span>
      </p>
    </div>
  )
}

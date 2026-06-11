import GistLogo from '../Common/GistLogo'
import { LOBSTER_FONT_FAMILY } from '../../constants/site'

const lobsterStyle = { fontFamily: LOBSTER_FONT_FAMILY, fontWeight: 400 as const }

type SearchGisterTaglineProps = {
  className?: string
}

export default function SearchGisterTagline({ className = '' }: SearchGisterTaglineProps) {
  return (
    <div className={`text-center px-4 ${className}`.trim()}>
      <p className="text-xl md:text-2xl text-page leading-snug tracking-tight text-center">
        <span className="font-bold" style={lobsterStyle}>
          gister.
        </span>
      </p>
      <p className="text-xl md:text-2xl text-page leading-snug tracking-tight text-center mt-1">
        <GistLogo as="span" size="inline" link={false} />
        <span className="font-serif">의 AI 에이전트</span>
      </p>
    </div>
  )
}

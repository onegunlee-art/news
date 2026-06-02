import type { ReactNode } from 'react'
import GistLogo from '../Common/GistLogo'
import SearchGisterTagline from './SearchGisterTagline'

type SearchLandingProps = {
  children?: ReactNode
  className?: string
  logoSize?: 'header' | 'default'
}

export default function SearchLanding({
  children,
  className = '',
  logoSize = 'header',
}: SearchLandingProps) {
  return (
    <div
      className={`flex flex-col flex-1 min-h-0 w-full max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto ${className}`.trim()}
    >
      <div className="flex flex-1 flex-col items-center justify-center px-4 py-8 min-h-[40vh]">
        <GistLogo link={false} size={logoSize} as="span" className="mb-8 md:mb-10" />
        <SearchGisterTagline />
      </div>
      {children ? (
        <div className="shrink-0 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-2 w-full">
          {children}
        </div>
      ) : null}
    </div>
  )
}

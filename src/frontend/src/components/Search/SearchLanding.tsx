import type { ReactNode } from 'react'
import SearchGisterTagline from './SearchGisterTagline'

type SearchLandingProps = {
  children?: ReactNode
  className?: string
}

export default function SearchLanding({
  children,
  className = '',
}: SearchLandingProps) {
  return (
    <div
      className={`flex flex-col flex-1 min-h-0 w-full max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto ${className}`.trim()}
    >
      <div
        className="flex w-full flex-col items-center px-4 max-md:flex-none max-md:justify-start max-md:pt-4 max-md:pb-2 md:flex-1 md:justify-center md:py-8 md:min-h-0"
      >
        <SearchGisterTagline className="mb-4 md:mb-6" />
        {children ? (
          <div className="mt-4 w-full max-md:block md:hidden">{children}</div>
        ) : null}
      </div>
      {children ? (
        <div className="hidden md:block shrink-0 w-full px-4 pt-2 pb-[max(1rem,env(safe-area-inset-bottom))]">
          {children}
        </div>
      ) : null}
    </div>
  )
}

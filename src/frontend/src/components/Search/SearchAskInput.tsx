import { FormEvent, useState } from 'react'
import GistMarkIcon from '../Common/GistMarkIcon'
import MaterialIcon from '../Common/MaterialIcon'
import { SEARCH_ASK_PLACEHOLDER, SEARCH_ENTRY_ICON } from '../../constants/site'

type SearchAskInputProps = {
  onSubmit: (query: string) => void
  autoFocus?: boolean
  disabled?: boolean
  className?: string
}

export default function SearchAskInput({
  onSubmit,
  autoFocus = false,
  disabled = false,
  className = '',
}: SearchAskInputProps) {
  const [query, setQuery] = useState('')

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    const trimmed = query.trim()
    if (!trimmed || disabled) return
    onSubmit(trimmed)
    setQuery('')
  }

  return (
    <form
      onSubmit={handleSubmit}
      className={`w-full ${className}`.trim()}
    >
      <div className="flex min-w-0 items-center gap-2 rounded-2xl border border-page bg-page px-3 py-2.5 shadow-sm dark:border-page dark:bg-page dark:shadow-md">
        <MaterialIcon
          name={SEARCH_ENTRY_ICON}
          className="shrink-0 text-page"
          size={22}
          aria-hidden
        />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={SEARCH_ASK_PLACEHOLDER}
          autoFocus={autoFocus}
          autoComplete="off"
          enterKeyHint="search"
          disabled={disabled}
          aria-label={SEARCH_ASK_PLACEHOLDER}
          className="min-w-0 flex-1 bg-transparent py-2 text-base font-serif text-page placeholder:text-page-muted focus:outline-none disabled:opacity-50"
        />
        {query.trim() !== '' && !disabled && (
          <button
            type="button"
            onClick={() => setQuery('')}
            className="shrink-0 rounded-full p-1 text-page hover:bg-page-secondary transition-colors"
            aria-label="입력 지우기"
          >
            <MaterialIcon name="close" className="w-5 h-5 text-page" size={20} />
          </button>
        )}
        <button
          type="submit"
          disabled={!query.trim() || disabled}
          className="shrink-0 flex h-11 w-11 items-center justify-center rounded-full border border-page bg-page hover:bg-page-secondary disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
          aria-label="검색"
        >
          <GistMarkIcon />
        </button>
      </div>
    </form>
  )
}

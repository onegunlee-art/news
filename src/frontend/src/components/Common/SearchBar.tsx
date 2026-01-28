import { useState, FormEvent } from 'react'

interface SearchBarProps {
  onSearch: (query: string) => void
  placeholder?: string
  initialValue?: string
}

export default function SearchBar({ 
  onSearch, 
  placeholder = '뉴스 검색...',
  initialValue = ''
}: SearchBarProps) {
  const [query, setQuery] = useState(initialValue)

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    onSearch(query.trim())
  }

  return (
    <form onSubmit={handleSubmit} className="relative max-w-xl mx-auto">
      <div className="relative">
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={placeholder}
          className="w-full px-6 py-4 pl-14 bg-dark-600 border border-white/10 rounded-2xl text-white placeholder-gray-500 focus:outline-none focus:border-primary-500/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
        />
        <div className="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        {query && (
          <button
            type="button"
            onClick={() => {
              setQuery('')
              onSearch('')
            }}
            className="absolute right-20 top-1/2 -translate-y-1/2 p-1 text-gray-500 hover:text-white transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}
        <button
          type="submit"
          className="absolute right-2 top-1/2 -translate-y-1/2 px-4 py-2 bg-primary-500 hover:bg-primary-400 text-white font-medium rounded-xl transition-colors"
        >
          검색
        </button>
      </div>

      {/* 검색 힌트 */}
      <div className="mt-3 flex flex-wrap justify-center gap-2 text-sm">
        <span className="text-gray-500">인기 검색어:</span>
        {['경제', '정치', 'AI', '주식'].map((keyword) => (
          <button
            key={keyword}
            type="button"
            onClick={() => {
              setQuery(keyword)
              onSearch(keyword)
            }}
            className="text-gray-400 hover:text-primary-400 transition-colors"
          >
            #{keyword}
          </button>
        ))}
      </div>
    </form>
  )
}

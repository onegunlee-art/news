import { useState, FormEvent } from 'react'
import MaterialIcon from './MaterialIcon'

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
          <MaterialIcon name="search" className="w-5 h-5" size={20} />
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
            <MaterialIcon name="close" className="w-5 h-5" size={20} />
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

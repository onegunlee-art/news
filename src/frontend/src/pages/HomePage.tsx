import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { newsApi } from '../services/api'
import SearchBar from '../components/Common/SearchBar'
import LoadingSpinner from '../components/Common/LoadingSpinner'

interface NewsItem {
  title: string
  description: string
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
}

export default function HomePage() {
  const [news, setNews] = useState<NewsItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState<NewsItem[] | null>(null)

  useEffect(() => {
    fetchNews()
  }, [])

  const fetchNews = async () => {
    setIsLoading(true)
    try {
      const response = await newsApi.getList(1, 20)
      if (response.data.success) {
        setNews(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Failed to fetch news:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const handleSearch = async (query: string) => {
    if (!query.trim()) {
      setSearchResults(null)
      return
    }

    setSearchQuery(query)
    setIsLoading(true)

    try {
      const response = await newsApi.search(query, 1, 20)
      if (response.data.success) {
        setSearchResults(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Search failed:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const clearSearch = () => {
    setSearchQuery('')
    setSearchResults(null)
  }

  const displayedNews = searchResults ?? news

  return (
    <div className="min-h-screen">
      {/* 히어로 섹션 */}
      <section className="relative py-16 lg:py-24 overflow-hidden">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="text-center max-w-3xl mx-auto"
          >
            <h1 className="font-display text-4xl lg:text-5xl font-bold mb-6">
              뉴스를 더 깊이
              <span className="block mt-2 bg-gradient-to-r from-primary-400 to-accent-purple bg-clip-text text-transparent">
                맥락으로 이해하다
              </span>
            </h1>
            <p className="text-gray-400 text-lg mb-8 leading-relaxed">
              전문가가 직접 짚어주는 뉴스의 이면과
              우리에게 전달될 파급력을 전해 드립니다.
            </p>

            {/* 검색 바 */}
            <SearchBar onSearch={handleSearch} />
          </motion.div>
        </div>
      </section>

      {/* 뉴스 피드 */}
      <section className="pb-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* 섹션 헤더 */}
          <div className="flex items-center justify-between mb-8">
            <div>
              {searchQuery ? (
                <h2 className="text-2xl font-bold text-white">
                  "{searchQuery}" 검색 결과
                </h2>
              ) : (
                <Link 
                  to="/news" 
                  className="group flex items-center gap-3 text-2xl font-bold text-white hover:text-primary-400 transition-colors"
                >
                  오늘의 맥락
                  <svg className="w-6 h-6 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                </Link>
              )}
              {searchQuery && (
                <button
                  onClick={clearSearch}
                  className="mt-2 text-sm text-primary-400 hover:text-primary-300 transition-colors"
                >
                  검색 초기화
                </button>
              )}
            </div>
            <Link
              to="/register"
              className="hidden sm:flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 rounded-lg text-white font-semibold transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              구독하기
            </Link>
          </div>

          {/* 로딩 상태 */}
          {isLoading ? (
            <div className="flex justify-center py-20">
              <LoadingSpinner size="large" />
            </div>
          ) : displayedNews.length === 0 ? (
            <div className="text-center py-20">
              <div className="text-gray-500 mb-4">
                <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                </svg>
              </div>
              <p className="text-gray-400">
                {searchQuery ? '검색 결과가 없습니다.' : '뉴스를 불러올 수 없습니다.'}
              </p>
            </div>
          ) : (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: 0.4 }}
            >
              {/* 3개만 멋지게 표시 */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 메인 뉴스 (크게) */}
                {displayedNews[0] && (
                  <motion.div
                    initial={{ opacity: 0, x: -30 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.5, delay: 0.1 }}
                    className="lg:row-span-2"
                  >
                    <FeaturedNewsCard news={displayedNews[0]} />
                  </motion.div>
                )}
                
                {/* 서브 뉴스 2개 */}
                <div className="space-y-6">
                  {displayedNews.slice(1, 3).map((item, index) => (
                    <motion.div
                      key={item.url + index}
                      initial={{ opacity: 0, x: 30 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ duration: 0.5, delay: 0.2 + index * 0.1 }}
                    >
                      <SubNewsCard news={item} />
                    </motion.div>
                  ))}
                </div>
              </div>
            </motion.div>
          )}
        </div>
      </section>

      {/* 구독 서비스 소개 */}
      <section className="py-16 bg-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-bold text-gray-900 text-center mb-12">구독 서비스</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <SubscriptionCard
              title="왜 이게 중요한대!"
              description="복잡한 뉴스의 핵심을 한눈에. 왜 이 뉴스가 당신에게 중요한지 명확하게 알려드립니다."
            />
            <SubscriptionCard
              title="빅픽쳐"
              description="개별 뉴스를 넘어 전체 흐름을 파악하세요. 글로벌 트렌드와 큰 그림을 제시합니다."
            />
            <SubscriptionCard
              title="그래서 우리에겐?"
              description="뉴스가 우리 일상과 사회에 미치는 영향을 분석하여 실질적인 인사이트를 제공합니다."
            />
          </div>
        </div>
      </section>
    </div>
  )
}

function SubscriptionCard({ 
  title, 
  description
}: { 
  title: string
  description: string
}) {
  return (
    <motion.div
      whileHover={{ y: -5 }}
      className="bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-shadow"
    >
      <h3 className="text-xl font-bold text-gray-900 mb-3">{title}</h3>
      <p className="text-gray-600 text-sm leading-relaxed">{description}</p>
    </motion.div>
  )
}

// 메인 뉴스 카드 (크게 표시)
function FeaturedNewsCard({ news }: { news: NewsItem }) {
  const imageId = Math.abs(news.title.split('').reduce((a, b) => a + b.charCodeAt(0), 0) % 1000) + 1
  const imageUrl = `https://picsum.photos/seed/${imageId}/800/600`
  
  return (
    <Link to={`/news/${(news as any).id || ''}`} className="group block h-full">
      <motion.article
        whileHover={{ scale: 1.02 }}
        transition={{ duration: 0.3 }}
        className="relative h-full min-h-[500px] rounded-2xl overflow-hidden shadow-xl"
      >
        {/* 배경 이미지 */}
        <img
          src={imageUrl}
          alt={news.title}
          className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
        />
        
        {/* 그라디언트 오버레이 */}
        <div className="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent"></div>
        
        {/* 콘텐츠 */}
        <div className="absolute bottom-0 left-0 right-0 p-8">
          {/* 출처 배지 */}
          {news.source && (
            <span className="inline-block px-3 py-1 bg-primary-500 text-white text-sm font-semibold rounded-full mb-4">
              {news.source}
            </span>
          )}
          
          {/* 제목 */}
          <h2 className="text-3xl lg:text-4xl font-bold text-white mb-4 leading-tight group-hover:text-primary-300 transition-colors">
            {news.title}
          </h2>
          
          {/* 설명 */}
          {news.description && (
            <p className="text-gray-200 text-lg line-clamp-2 mb-4">
              {news.description}
            </p>
          )}
          
          {/* 하단 정보 */}
          <div className="flex items-center justify-between">
            <span className="text-gray-400 text-sm">
              {news.time_ago || (news.published_at && new Date(news.published_at).toLocaleDateString('ko-KR'))}
            </span>
            <span className="flex items-center gap-2 text-primary-400 font-medium group-hover:gap-3 transition-all">
              자세히 보기
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </span>
          </div>
        </div>
      </motion.article>
    </Link>
  )
}

// 서브 뉴스 카드 (작게 표시)
function SubNewsCard({ news }: { news: NewsItem }) {
  const imageId = Math.abs(news.title.split('').reduce((a, b) => a + b.charCodeAt(0), 0) % 1000) + 1
  const imageUrl = `https://picsum.photos/seed/${imageId}/400/300`
  
  return (
    <Link to={`/news/${(news as any).id || ''}`} className="group block">
      <motion.article
        whileHover={{ x: 10 }}
        transition={{ duration: 0.2 }}
        className="flex gap-5 bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-all p-4"
      >
        {/* 썸네일 */}
        <div className="relative w-32 h-32 flex-shrink-0 rounded-lg overflow-hidden">
          <img
            src={imageUrl}
            alt={news.title}
            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
          />
          <div className="absolute inset-0 bg-black/10 group-hover:bg-black/0 transition-colors"></div>
        </div>
        
        {/* 콘텐츠 */}
        <div className="flex flex-col justify-center flex-1 min-w-0">
          {/* 출처 & 시간 */}
          <div className="flex items-center gap-2 mb-2">
            {news.source && (
              <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full font-medium">
                {news.source}
              </span>
            )}
            <span className="text-xs text-gray-400">
              {news.time_ago || (news.published_at && new Date(news.published_at).toLocaleDateString('ko-KR'))}
            </span>
          </div>
          
          {/* 제목 */}
          <h3 className="text-lg font-bold text-gray-900 line-clamp-2 mb-2 group-hover:text-primary-600 transition-colors">
            {news.title}
          </h3>
          
          {/* 설명 */}
          {news.description && (
            <p className="text-gray-500 text-sm line-clamp-1">
              {news.description}
            </p>
          )}
        </div>
        
        {/* 화살표 아이콘 */}
        <div className="flex items-center text-gray-300 group-hover:text-primary-500 transition-colors">
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </motion.article>
    </Link>
  )
}

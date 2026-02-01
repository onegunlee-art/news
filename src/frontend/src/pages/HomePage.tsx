import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { newsApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'

interface NewsItem {
  id?: number
  title: string
  description: string
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  category?: string
}

export default function HomePage() {
  const [news, setNews] = useState<NewsItem[]>([])
  const [isLoading, setIsLoading] = useState(true)

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

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <LoadingSpinner size="large" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-white">
      {/* 메인 히어로 섹션 */}
      <section className="border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 py-8">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
            {/* 메인 기사 */}
            {news[0] && (
              <div className="lg:col-span-7">
                <FeaturedArticle article={news[0]} />
              </div>
            )}
            
            {/* 사이드 기사들 */}
            <div className="lg:col-span-5 space-y-6">
              {news.slice(1, 4).map((item, index) => (
                <SideArticle key={item.id || index} article={item} />
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* 최신 기사 그리드 */}
      <section className="py-16">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex items-center gap-4 mb-10">
            <div className="w-1 h-8 bg-primary-500 rounded-full" />
            <h2 className="text-2xl font-semibold text-gray-900 tracking-tight">
              최신 분석
            </h2>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
            {news.slice(4, 10).map((item, index) => (
              <ArticleCard key={item.id || index} article={item} />
            ))}
          </div>
        </div>
      </section>

      {/* 구독 CTA */}
      <section className="relative bg-gradient-to-br from-gray-900 via-gray-900 to-gray-800 py-20 overflow-hidden">
        {/* 배경 패턴 */}
        <div className="absolute inset-0 opacity-5">
          <div className="absolute top-0 left-0 w-96 h-96 bg-primary-500 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2" />
          <div className="absolute bottom-0 right-0 w-96 h-96 bg-primary-500 rounded-full blur-3xl translate-x-1/2 translate-y-1/2" />
        </div>
        
        <div className="relative max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-3xl md:text-4xl font-semibold text-white mb-5" style={{ letterSpacing: '-0.02em' }}>
            뉴스의 본질을 파악하세요
          </h2>
          <p className="text-gray-400 text-lg mb-10 max-w-2xl mx-auto leading-relaxed">
            전문가가 직접 짚어주는 뉴스의 이면과 우리에게 전달될 파급력을 전해 드립니다.
          </p>
          <Link
            to="/subscribe"
            className="inline-block px-10 py-4 bg-primary-500 hover:bg-primary-400 text-white font-semibold rounded-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary-500/25 hover:-translate-y-0.5"
          >
            구독하기
          </Link>
        </div>
      </section>

      {/* 카테고리 섹션 */}
      <section className="py-12 border-t border-gray-200">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <CategorySection title="Foreign Affairs" link="/diplomacy" />
            <CategorySection title="Economy" link="/economy" />
            <CategorySection title="Technology" link="/technology" />
            <CategorySection title="Entertainment" link="/entertainment" />
          </div>
        </div>
      </section>
    </div>
  )
}

// 메인 피처드 기사
function FeaturedArticle({ article }: { article: NewsItem }) {
  const imageId = Math.abs(article.title.split('').reduce((a, b) => a + b.charCodeAt(0), 0) % 1000) + 1
  const imageUrl = `https://picsum.photos/seed/${imageId}/800/500`

  return (
    <Link to={`/news/${article.id || ''}`} className="group block">
      <motion.article
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6, ease: 'easeOut' }}
        className="relative"
      >
        {/* 이미지 */}
        <div className="aspect-[16/10] overflow-hidden mb-6 relative rounded-sm shadow-sm">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]"
          />
          {/* 하단 그라데이션 오버레이 */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
        </div>
        
        {/* 콘텐츠 */}
        <div>
          {article.source && (
            <span className="text-[11px] font-semibold text-primary-500 uppercase tracking-[0.15em]">
              {article.source}
            </span>
          )}
          <h2 className="text-3xl md:text-4xl font-semibold text-gray-900 mt-2 mb-4 leading-tight group-hover:text-gray-700 transition-colors duration-300" style={{ letterSpacing: '-0.02em' }}>
            {article.title}
          </h2>
          {article.description && (
            <p className="text-gray-500 text-lg leading-relaxed line-clamp-3">
              {article.description}
            </p>
          )}
          <p className="text-xs text-gray-400 mt-5 uppercase tracking-wide">
            {article.time_ago || (article.published_at && new Date(article.published_at).toLocaleDateString('en-US', { 
              month: 'long', 
              day: 'numeric', 
              year: 'numeric' 
            }))}
          </p>
        </div>
      </motion.article>
    </Link>
  )
}

// 사이드 기사
function SideArticle({ article }: { article: NewsItem }) {
  const imageId = Math.abs(article.title.split('').reduce((a, b) => a + b.charCodeAt(0), 0) % 1000) + 1
  const imageUrl = `https://picsum.photos/seed/${imageId}/300/200`

  return (
    <Link to={`/news/${article.id || ''}`} className="group block">
      <article className="flex gap-5 pb-6 border-b border-gray-50 last:border-0 transition-transform duration-300 group-hover:translate-x-1">
        {/* 썸네일 */}
        <div className="w-32 h-24 flex-shrink-0 overflow-hidden rounded-sm shadow-sm">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.05]"
          />
        </div>
        
        {/* 콘텐츠 */}
        <div className="flex-1 min-w-0 flex flex-col justify-center">
          {article.source && (
            <span className="text-[10px] font-semibold text-primary-500 uppercase tracking-[0.12em]">
              {article.source}
            </span>
          )}
          <h3 className="text-base font-medium text-gray-900 leading-snug mt-1 group-hover:text-gray-700 transition-colors duration-200 line-clamp-2" style={{ letterSpacing: '-0.01em' }}>
            {article.title}
          </h3>
          <p className="text-[10px] text-gray-400 mt-2 uppercase tracking-wide">
            {article.time_ago || (article.published_at && new Date(article.published_at).toLocaleDateString('en-US', { 
              month: 'short', 
              day: 'numeric'
            }))}
          </p>
        </div>
      </article>
    </Link>
  )
}

// 기사 카드
function ArticleCard({ article }: { article: NewsItem }) {
  const imageId = Math.abs(article.title.split('').reduce((a, b) => a + b.charCodeAt(0), 0) % 1000) + 1
  const imageUrl = `https://picsum.photos/seed/${imageId}/400/250`

  return (
    <Link to={`/news/${article.id || ''}`} className="group block">
      <motion.article
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        whileHover={{ y: -6 }}
        transition={{ duration: 0.4, ease: 'easeOut' }}
        className="bg-white rounded-sm border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        {/* 이미지 */}
        <div className="aspect-[16/10] overflow-hidden">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]"
          />
        </div>
        
        {/* 콘텐츠 */}
        <div className="p-5">
          {article.source && (
            <span className="text-[10px] font-semibold text-primary-500 uppercase tracking-[0.12em]">
              {article.source}
            </span>
          )}
          <h3 className="text-lg font-semibold text-gray-900 mt-2 mb-2 leading-snug group-hover:text-gray-700 transition-colors duration-200 line-clamp-2" style={{ letterSpacing: '-0.01em' }}>
            {article.title}
          </h3>
          {article.description && (
            <p className="text-gray-500 text-sm leading-relaxed line-clamp-2">
              {article.description}
            </p>
          )}
        </div>
      </motion.article>
    </Link>
  )
}

// 카테고리 섹션
function CategorySection({ title, link }: { title: string; link: string }) {
  const descriptions: Record<string, string> = {
    'Foreign Affairs': '국제 관계와 외교 정책에 대한 전문 분석',
    'Economy': '경제 동향과 금융 시장에 대한 심층 분석',
    'Technology': '기술 혁신과 IT 산업에 대한 전문 분석',
    'Entertainment': '문화와 엔터테인먼트 산업에 대한 분석',
  }
  
  return (
    <div>
      <Link 
        to={link}
        className="flex items-center justify-between pb-3 border-b-2 border-gray-900 mb-4 group"
      >
        <h3 className="text-lg font-medium text-gray-900 group-hover:text-primary-500 transition-colors">
          {title}
        </h3>
        <svg className="w-5 h-5 text-gray-400 group-hover:text-primary-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
        </svg>
      </Link>
      <p className="text-sm text-gray-500">
        {descriptions[title] || '전문가 분석과 인사이트'}
      </p>
    </div>
  )
}

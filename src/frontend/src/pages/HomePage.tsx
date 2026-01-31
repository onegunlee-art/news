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
      <section className="py-12">
        <div className="max-w-7xl mx-auto px-4">
          <h2 className="font-serif text-2xl font-bold text-gray-900 mb-8 pb-4 border-b border-gray-200">
            최신 분석
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {news.slice(4, 10).map((item, index) => (
              <ArticleCard key={item.id || index} article={item} />
            ))}
          </div>
        </div>
      </section>

      {/* 구독 CTA */}
      <section className="bg-gray-900 py-16">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-3xl md:text-4xl font-semibold text-white mb-4">
            뉴스의 본질을 파악하세요
          </h2>
          <p className="text-gray-400 text-lg mb-8 max-w-2xl mx-auto">
            전문가가 직접 짚어주는 뉴스의 이면과 우리에게 전달될 파급력을 전해 드립니다.
          </p>
          <Link
            to="/subscribe"
            className="inline-block px-8 py-4 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded transition-colors"
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
        transition={{ duration: 0.5 }}
      >
        {/* 이미지 */}
        <div className="aspect-[16/10] overflow-hidden mb-6">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          />
        </div>
        
        {/* 콘텐츠 */}
        <div>
          {article.source && (
            <span className="text-xs font-semibold text-primary-500 uppercase tracking-wider">
              {article.source}
            </span>
          )}
          <h2 className="font-serif text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4 leading-tight group-hover:text-primary-500 transition-colors">
            {article.title}
          </h2>
          {article.description && (
            <p className="text-gray-600 text-lg leading-relaxed line-clamp-3">
              {article.description}
            </p>
          )}
          <p className="text-sm text-gray-400 mt-4">
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
      <article className="flex gap-4 pb-6 border-b border-gray-100 last:border-0">
        {/* 썸네일 */}
        <div className="w-32 h-24 flex-shrink-0 overflow-hidden">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        </div>
        
        {/* 콘텐츠 */}
        <div className="flex-1 min-w-0">
          {article.source && (
            <span className="text-xs font-semibold text-primary-500 uppercase tracking-wider">
              {article.source}
            </span>
          )}
          <h3 className="text-lg font-medium text-gray-900 leading-snug mt-1 group-hover:text-primary-500 transition-colors line-clamp-2">
            {article.title}
          </h3>
          <p className="text-xs text-gray-400 mt-2">
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
        whileHover={{ y: -4 }}
        transition={{ duration: 0.3 }}
      >
        {/* 이미지 */}
        <div className="aspect-[16/10] overflow-hidden mb-4">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        </div>
        
        {/* 콘텐츠 */}
        <div>
          {article.source && (
            <span className="text-xs font-semibold text-primary-500 uppercase tracking-wider">
              {article.source}
            </span>
          )}
          <h3 className="font-serif text-xl font-bold text-gray-900 mt-2 mb-2 leading-snug group-hover:text-primary-500 transition-colors line-clamp-2">
            {article.title}
          </h3>
          {article.description && (
            <p className="text-gray-600 text-sm leading-relaxed line-clamp-2">
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

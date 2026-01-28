import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useMemo } from 'react'

interface NewsItem {
  id?: number
  title: string
  description: string | null
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  image_url?: string | null
  category?: string | null
}

interface NewsCardProps {
  news: NewsItem
  index?: number
}

// 제목/카테고리에서 이미지 ID 생성 (일관된 이미지 표시를 위해)
function getImageId(title: string, category?: string | null): number {
  // 문자열의 해시값을 기반으로 1-1000 사이의 숫자 생성
  let hash = 0
  const str = title + (category || '')
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash = hash & hash
  }
  return Math.abs(hash % 1000) + 1
}

// 카테고리별 이미지 색상/스타일 (Lorem Picsum 사용)
function getPlaceholderImageUrl(title: string, category?: string | null): string {
  const id = getImageId(title, category)
  // Lorem Picsum - 무료 랜덤 이미지 서비스
  return `https://picsum.photos/seed/${id}/400/250`
}

export default function NewsCard({ news, index = 0 }: NewsCardProps) {
  // 이미지 URL 생성 (메모이제이션)
  const imageUrl = useMemo(() => {
    if (news.image_url) return news.image_url
    return getPlaceholderImageUrl(news.title, news.category)
  }, [news.title, news.image_url, news.category])

  const content = (
    <motion.article
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.05 }}
      className="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow h-full flex flex-col overflow-hidden"
    >
      {/* 썸네일 이미지 */}
      <div className="relative h-48 overflow-hidden bg-gray-100">
        <img
          src={imageUrl}
          alt={news.title}
          className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          loading="lazy"
          onError={(e) => {
            // 이미지 로드 실패 시 기본 이미지
            (e.target as HTMLImageElement).src = 'https://picsum.photos/400/250?grayscale'
          }}
        />
        {/* 출처 배지 */}
        {news.source && (
          <span className="absolute top-3 left-3 px-2 py-1 bg-black/70 text-white text-xs font-medium rounded">
            {news.source}
          </span>
        )}
      </div>

      {/* 콘텐츠 */}
      <div className="p-4 flex flex-col flex-1">
        {/* 시간 */}
        {news.time_ago && (
          <span className="text-xs text-gray-500 mb-2">{news.time_ago}</span>
        )}

        {/* 제목 */}
        <h3 className="text-lg font-bold text-gray-900 mb-2 line-clamp-2 leading-snug group-hover:text-primary-600 transition-colors">
          {news.title}
        </h3>

        {/* 설명 */}
        {news.description && (
          <p className="text-gray-600 text-sm line-clamp-2 mb-4 flex-1">
            {news.description}
          </p>
        )}

        {/* 하단 */}
        <div className="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
          <span className="text-xs text-gray-400">
            {news.published_at && new Date(news.published_at).toLocaleDateString('ko-KR')}
          </span>
          <span className="text-primary-600 text-sm font-medium flex items-center gap-1">
            자세히 보기
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </span>
        </div>
      </div>
    </motion.article>
  )

  // ID가 있으면 내부 링크, 없으면 외부 링크
  if (news.id) {
    return (
      <Link to={`/news/${news.id}`} className="group block h-full">
        {content}
      </Link>
    )
  }

  return (
    <a 
      href={news.url} 
      target="_blank" 
      rel="noopener noreferrer" 
      className="group block h-full"
    >
      {content}
    </a>
  )
}

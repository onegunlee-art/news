import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useMemo } from 'react'
import MaterialIcon from '../Common/MaterialIcon'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'
import { formatSourceDisplayName } from '../../utils/formatSource'
import { stripHtml } from '../../utils/sanitizeHtml'
import { newsDetailPath } from '../../utils/newsDetailLink'

interface NewsItem {
  id?: number
  title: string
  description: string | null
  url: string
  source: string | null
  /** 표시용 날짜 (created_at 기준). docs/DATE_POLICY.md */
  display_date?: string | null
  published_at: string | null
  time_ago?: string
  image_url?: string | null
  category?: string | null
}

interface NewsCardProps {
  news: NewsItem
  index?: number
  /** 기사 상세 진입 시 from_tab 등 prev/next 정렬에 사용할 state */
  linkState?: Record<string, unknown>
}

export default function NewsCard({ news, index = 0, linkState }: NewsCardProps) {
  const tabLabel =
    linkState && typeof linkState.fromTab === 'string' ? linkState.fromTab : undefined
  // 이미지 URL: 저장된 image_url 우선, 없으면 기사별 고유 시드로 placeholder (중복 없음)
  const imageUrl = useMemo(() => {
    if (news.image_url) return news.image_url
    return getPlaceholderImageUrl(
      { id: news.id, title: news.title, description: news.description, published_at: news.published_at, category: news.category },
      400,
      250
    )
  }, [news.id, news.title, news.description, news.published_at, news.category, news.image_url])

  const content = (
    <motion.article
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.05 }}
      className="bg-page rounded-xl shadow-md hover:shadow-lg transition-shadow h-full flex flex-col overflow-hidden"
    >
      {/* 썸네일 이미지 - 정사각형(1:1) 정책 */}
      <div className="relative aspect-square w-full overflow-hidden bg-page-secondary">
        <img
          src={imageUrl}
          alt={news.title}
          className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          loading="lazy"
          onError={(e) => {
            (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
              { id: news.id, title: news.title, description: news.description, published_at: news.published_at, category: news.category, url: news.url, source: news.source },
              400,
              250
            )
          }}
        />
        {/* 출처 배지 */}
        {news.source && (
          <span className="absolute top-3 left-3 px-2 py-1 bg-primary-500/95 text-white text-xs font-medium rounded">
            {formatSourceDisplayName(news.source)}
          </span>
        )}
      </div>

      {/* 콘텐츠 (흑백 적용) */}
      <div className="p-4 flex flex-col flex-1">
        {/* 시간 */}
        {news.time_ago && (
          <span className="text-xs text-page-secondary mb-2">{news.time_ago}</span>
        )}

        {/* 제목 */}
        <h3 className="text-lg font-bold text-page mb-2 line-clamp-2 leading-snug group-hover:text-primary-600 transition-colors">
          {news.title}
        </h3>

        {/* 설명 */}
        {news.description && (
          <p className="text-page-secondary text-sm line-clamp-2 mb-4 flex-1">
            {stripHtml(news.description)}
          </p>
        )}

        {/* 하단 */}
        <div className="flex items-center justify-between mt-auto pt-3 border-t border-page">
          <span className="text-xs text-page-muted">
            {(news.display_date ?? news.published_at)
              ? new Date(news.display_date ?? news.published_at!).toLocaleDateString('ko-KR')
              : null}
          </span>
          <span className="text-primary-600 text-sm font-medium flex items-center gap-1">
            자세히 보기
            <MaterialIcon name="chevron_right" className="w-4 h-4" size={16} />
          </span>
        </div>
      </div>
    </motion.article>
  )

  // ID가 있으면 내부 링크, 없으면 외부 링크
  if (news.id) {
    return (
      <Link to={newsDetailPath(news.id, tabLabel)} state={linkState} className="group block h-full">
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

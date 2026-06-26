import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useMemo } from 'react'
import type { SeriesCover } from '../../services/api'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'
import { newsDetailPath } from '../../utils/newsDetailLink'

interface SeriesCoverCardProps {
  cover: SeriesCover
  index?: number
}

export default function SeriesCoverCard({ cover, index = 0 }: SeriesCoverCardProps) {
  const imageUrl = useMemo(() => {
    if (cover.first_article_image) return cover.first_article_image
    return getPlaceholderImageUrl(
      {
        id: cover.first_article_id ?? undefined,
        title: cover.series_title ?? 'Series',
        description: cover.cover_text,
        published_at: cover.created_at,
        category: null,
      },
      400,
      500
    )
  }, [cover.first_article_image, cover.first_article_id, cover.series_title, cover.cover_text, cover.created_at])

  const textStyle = useMemo(() => ({
    color: cover.text_color || '#ffffff',
    fontSize: `${cover.text_size || 24}px`,
    left: `${cover.text_x || 50}%`,
    top: `${cover.text_y || 50}%`,
    transform: 'translate(-50%, -50%)',
    textShadow: '0 2px 8px rgba(0,0,0,0.7)',
  }), [cover.text_color, cover.text_size, cover.text_x, cover.text_y])

  const content = (
    <motion.article
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.08 }}
      className="relative rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group cursor-pointer"
    >
      <div className="relative aspect-[3/4] w-full overflow-hidden">
        <img
          src={imageUrl}
          alt={cover.series_title || '시리즈'}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
        
        {cover.cover_text && (
          <span
            className="absolute font-bold leading-tight whitespace-pre-wrap text-center max-w-[90%] pointer-events-none"
            style={textStyle}
          >
            {cover.cover_text}
          </span>
        )}

        {cover.series_title && (
          <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/80 to-transparent">
            <h3 className="text-white text-lg font-semibold line-clamp-2 leading-snug">
              {cover.series_title}
            </h3>
            {typeof cover.article_count === 'number' && cover.article_count > 0 && (
              <p className="text-white/80 text-sm mt-1">
                {cover.article_count}개의 기사
              </p>
            )}
          </div>
        )}
      </div>
    </motion.article>
  )

  if (cover.first_article_id) {
    return (
      <Link
        to={newsDetailPath(cover.first_article_id, '과거 특집')}
        state={{ fromTab: '과거 특집' }}
        className="block h-full"
      >
        {content}
      </Link>
    )
  }

  return content
}

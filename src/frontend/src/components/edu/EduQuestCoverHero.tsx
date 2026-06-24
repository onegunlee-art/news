import { useMemo, useState } from 'react'
import { getPlaceholderImageUrl, type ArticleForImage } from '../../utils/imagePolicy'

export type EduQuestCoverHeroProps = {
  coverImageUrl?: string | null
  questTitle: string
  hookShort?: string | null
  timeAnchor?: string | null
  conflictSummary?: string | null
  coverArticle?: { news_id: number; title: string; gist_url?: string } | null
  variant?: 'hero' | 'card'
  topicLabel?: string
}

function coverPlaceholderArticle(
  questTitle: string,
  coverArticle?: EduQuestCoverHeroProps['coverArticle'],
): ArticleForImage {
  return {
    id: coverArticle?.news_id ?? null,
    title: coverArticle?.title || questTitle,
    url: coverArticle?.gist_url ?? null,
  }
}

export default function EduQuestCoverHero({
  coverImageUrl,
  questTitle,
  hookShort,
  timeAnchor,
  conflictSummary,
  coverArticle,
  variant = 'hero',
  topicLabel = '오늘 따질 주제',
}: EduQuestCoverHeroProps) {
  const [imgFailed, setImgFailed] = useState(false)
  const topicLine = (hookShort?.trim() || questTitle).trim()
  const articleForImage = useMemo(
    () => coverPlaceholderArticle(questTitle, coverArticle),
    [questTitle, coverArticle],
  )
  const width = variant === 'hero' ? 800 : 640
  const height = variant === 'hero' ? 450 : 360
  const placeholder = useMemo(
    () => getPlaceholderImageUrl(articleForImage, width, height),
    [articleForImage, width, height],
  )
  const src = !imgFailed && coverImageUrl ? coverImageUrl : placeholder

  return (
    <section
      className={`overflow-hidden bg-[#1a1a1a] ${
        variant === 'hero' ? 'rounded-xl border border-[#333]' : 'rounded-none border-0'
      }`}
    >
      <div className={`relative w-full ${variant === 'hero' ? 'aspect-[16/9]' : 'aspect-[5/3]'}`}>
        <img
          src={src}
          alt=""
          loading="eager"
          decoding="async"
          fetchPriority="high"
          className="absolute inset-0 h-full w-full object-cover edu-quest-cover-img"
          onError={() => setImgFailed(true)}
        />
        <div
          className="absolute inset-0 bg-gradient-to-t from-[#0D0D0D] via-[#0D0D0D]/55 to-transparent"
          aria-hidden
        />
        <div className="absolute inset-x-0 bottom-0 p-4 pt-10 space-y-1.5">
          <p className="text-[11px] font-bold tracking-wide text-[#E8521C] uppercase edu-game-text-ko">
            {topicLabel}
          </p>
          {timeAnchor && (
            <p className="text-[11px] text-[#aaa] edu-game-text-ko">{timeAnchor}</p>
          )}
          <h2
            className={`font-bold leading-snug text-white edu-game-text-ko ${
              variant === 'hero' ? 'text-xl' : 'text-base'
            }`}
          >
            {topicLine}
          </h2>
          {conflictSummary && variant === 'hero' && (
            <p className="text-sm text-[#bbb] line-clamp-2 edu-game-text-ko">{conflictSummary}</p>
          )}
        </div>
      </div>
    </section>
  )
}

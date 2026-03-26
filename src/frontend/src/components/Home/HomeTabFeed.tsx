import { useMemo } from 'react'
import LoadingSpinner from '../Common/LoadingSpinner'
import { useInfiniteNewsList, usePopularNews } from '../../hooks/useNews'
import HomeArticleCard, { type HomeNewsItem } from './HomeArticleCard'

const PER_PAGE = 20

function filterPlaceholder(items: HomeNewsItem[]): HomeNewsItem[] {
  const placeholderPhrases = ['무엇이 처음부터 왔었']
  return items.filter(
    (item: HomeNewsItem) =>
      !placeholderPhrases.some(
        (phrase) =>
          (item.title && item.title.includes(phrase)) ||
          (item.description && item.description.includes(phrase)) ||
          (item.narration && item.narration.includes(phrase))
      )
  )
}

function chunkBy2<T>(arr: T[]): T[][] {
  const out: T[][] = []
  for (let i = 0; i < arr.length; i += 2) {
    out.push(arr.slice(i, i + 2))
  }
  return out
}

export interface HomeTabFeedProps {
  tabLabel: string
  popularLabel: string
  category: string | undefined
  subCategoryToLabel: Record<string, string>
  /** 활성 탭 ±1 윈도우에서만 true — API 부하 제한 */
  fetchEnabled: boolean
}

export default function HomeTabFeed({
  tabLabel,
  popularLabel,
  category,
  subCategoryToLabel,
  fetchEnabled,
}: HomeTabFeedProps) {
  const isPopularTab = tabLabel === popularLabel

  const {
    data: infiniteData,
    isLoading: isLoadingInfinite,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
  } = useInfiniteNewsList(category, PER_PAGE, fetchEnabled && !isPopularTab)

  const { data: popularData, isLoading: isLoadingPopular } = usePopularNews(
    fetchEnabled && isPopularTab
  )

  const news = useMemo(() => {
    if (isPopularTab) {
      const items = popularData?.data?.items || []
      return filterPlaceholder(items as HomeNewsItem[])
    }
    const pages = infiniteData?.pages || []
    const allItems = pages.flatMap((page) => page.data?.items || [])
    return filterPlaceholder(allItems as HomeNewsItem[])
  }, [isPopularTab, popularData, infiniteData])

  const isLoading = isPopularTab ? isLoadingPopular : isLoadingInfinite
  const isLoadingMore = isFetchingNextPage
  const rowChunks = useMemo(() => chunkBy2(news), [news])

  const loadMore = () => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage()
    }
  }

  if (!fetchEnabled) {
    return <div className="min-h-[40vh]" aria-hidden />
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <LoadingSpinner size="large" />
      </div>
    )
  }

  if (news.length === 0) {
    return (
      <div className="text-center py-20 text-page-secondary">
        기사가 없습니다.
      </div>
    )
  }

  return (
    <>
      <div className="bg-page">
        <div className="lg:hidden">
          {news.map((item, i) => (
            <div key={item.id ?? i}>
              <HomeArticleCard article={item} activeTab={tabLabel} subCategoryToLabel={subCategoryToLabel} />
              {i < news.length - 1 && (
                <div className="h-2 bg-page-secondary" aria-hidden />
              )}
            </div>
          ))}
        </div>
        <div className="hidden lg:block">
          {rowChunks.map((row, rowIndex) => (
            <div key={rowIndex}>
              <div className="grid grid-cols-2 gap-x-12">
                {row.map((item, idx) => (
                  <HomeArticleCard
                    key={item.id ?? rowIndex * 2 + idx}
                    article={item}
                    activeTab={tabLabel}
                    subCategoryToLabel={subCategoryToLabel}
                  />
                ))}
              </div>
              {rowIndex < rowChunks.length - 1 && (
                <div className="h-2 bg-page-secondary" aria-hidden />
              )}
            </div>
          ))}
        </div>
      </div>
      {hasNextPage && !isPopularTab && (
        <div className="flex justify-center pt-8 pb-4">
          <button
            type="button"
            onClick={loadMore}
            disabled={isLoadingMore}
            className="text-page-secondary hover:text-page underline-offset-2 hover:underline font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoadingMore ? '불러오는 중...' : '더 보기'}
          </button>
        </div>
      )}
    </>
  )
}

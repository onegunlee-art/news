import { useState, useMemo } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import MaterialIcon from '../components/Common/MaterialIcon'
import { newsApi } from '../services/api'
import ShareMenu from '../components/Common/ShareMenu'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { formatSourceDisplayName, buildEditorialLine } from '../utils/formatSource'
import { extractTitleFromUrl } from '../utils/extractTitleFromUrl'
import { stripHtml } from '../utils/sanitizeHtml'
import { queryKeys } from '../lib/queryClient'
import { useMenuConfig } from '../hooks/useMenuConfig'
import { apiErrorMessage } from '../utils/apiErrorMessage'
import { newsDetailPath } from '../utils/newsDetailLink'

interface NewsItem {
  id?: number
  title: string
  description: string
  url: string
  source: string | null
  display_date?: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
  original_source?: string | null
  narration?: string | null
}

interface SemanticResult {
  news_id: number
  similarity: number
  topic_label: string
  topic_category: string
  entities: string[]
  region: string[]
  title: string
  description: string
  image_url: string
  published_at: string
  category: string
}

interface SearchCluster {
  name: string
  article_indices: number[]
  hero_index: number
}

interface SemanticSearchResponse {
  success: boolean
  results: SemanticResult[]
  insight: string | null
  clusters: SearchCluster[]
  meta: {
    query: string
    total: number
    filter_category: string | null
  }
}

const TOPIC_CATEGORIES = ['무역', '외교', '군사', '에너지', '금융', '기술', '정치'] as const

/** useMemo 의존성 안정화용 (매 렌더마다 []를 쓰면 exhaustive-deps 경고 발생) */
const EMPTY_SEMANTIC_RESULTS: SemanticResult[] = []

export default function SearchPage() {
  const [searchParams] = useSearchParams()
  const q = searchParams.get('q')?.trim() ?? ''
  const [searchMode, setSearchMode] = useState<'ai' | 'keyword'>('ai')
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null)

  const { subCategoryToLabel } = useMenuConfig()

  // 키워드 검색 (기존)
  const keywordQuery = useQuery({
    queryKey: queryKeys.news.search(q),
    queryFn: async () => {
      const res = await newsApi.search(q, 1, 30)
      if (res.data.success && res.data.data?.items) {
        return res.data.data.items as NewsItem[]
      }
      return []
    },
    enabled: q.length >= 1 && searchMode === 'keyword',
    staleTime: 1000 * 60 * 2,
  })

  // AI 벡터 검색
  const semanticQuery = useQuery({
    queryKey: ['semanticSearch', q, selectedCategory],
    queryFn: async () => {
      const res = await newsApi.semanticSearch(q, selectedCategory ?? undefined, 20)
      return res.data as SemanticSearchResponse
    },
    enabled: q.length >= 1 && searchMode === 'ai',
    staleTime: 1000 * 60 * 2,
  })

  const isLoading = searchMode === 'ai' ? semanticQuery.isLoading : keywordQuery.isLoading
  const isFetched = searchMode === 'ai' ? semanticQuery.isFetched : keywordQuery.isFetched
  const searched = isFetched && q.length >= 1

  const keywordResults = keywordQuery.data ?? []
  const semanticData = semanticQuery.data
  const semanticResults = semanticData?.results ?? EMPTY_SEMANTIC_RESULTS
  const insight = semanticData?.insight ?? null
  const clusters = semanticData?.clusters ?? []

  const availableCategories = useMemo(() => {
    const list = semanticData?.results
    if (!list?.length) return []
    const cats = new Set(list.map((r) => r.topic_category).filter(Boolean))
    return TOPIC_CATEGORIES.filter((c) => cats.has(c))
  }, [semanticData])

  const resultCount = searchMode === 'ai' ? semanticResults.length : keywordResults.length

  return (
    <div className="min-h-screen bg-page pb-8">
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-6 md:pt-8">
        {/* 헤더 */}
        <div className="mb-4">
          <h1 className="text-xl md:text-2xl font-semibold text-page">
            {q ? `「${q}」에 대한 결과` : '검색'}
          </h1>
          {q && searched && !isLoading && (
            <p className="text-sm text-page-secondary mt-1.5 font-medium">{resultCount}건</p>
          )}
        </div>

        {/* 검색 모드 토글 */}
        {q && (
          <div className="flex gap-2 mb-4">
            <button
              type="button"
              onClick={() => setSearchMode('ai')}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                searchMode === 'ai'
                  ? 'bg-primary-500 text-white'
                  : 'bg-page-secondary text-page-secondary hover:text-page'
              }`}
            >
              AI 검색
            </button>
            <button
              type="button"
              onClick={() => setSearchMode('keyword')}
              className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                searchMode === 'keyword'
                  ? 'bg-primary-500 text-white'
                  : 'bg-page-secondary text-page-secondary hover:text-page'
              }`}
            >
              키워드 검색
            </button>
          </div>
        )}

        {/* AI 검색 카테고리 필터 */}
        {q && searchMode === 'ai' && availableCategories.length > 0 && (
          <div className="flex gap-2 mb-5 overflow-x-auto pb-1 scrollbar-hide">
            <button
              type="button"
              onClick={() => setSelectedCategory(null)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors ${
                selectedCategory === null
                  ? 'bg-primary-500 text-white'
                  : 'bg-page-secondary text-page-secondary hover:text-page'
              }`}
            >
              전체
            </button>
            {availableCategories.map((cat) => (
              <button
                key={cat}
                type="button"
                onClick={() => setSelectedCategory(selectedCategory === cat ? null : cat)}
                className={`px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors ${
                  selectedCategory === cat
                    ? 'bg-primary-500 text-white'
                    : 'bg-page-secondary text-page-secondary hover:text-page'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>
        )}

        {/* 메인 콘텐츠 */}
        {!q ? (
          <EmptySearchState />
        ) : isLoading ? (
          <div className="flex flex-col justify-center items-center py-20 gap-3">
            <LoadingSpinner size="large" />
            {searchMode === 'ai' && (
              <p className="text-sm text-page-secondary">AI가 검색어를 분석 중...</p>
            )}
          </div>
        ) : searchMode === 'ai' ? (
          <AISearchResults
            results={semanticResults}
            insight={insight}
            clusters={clusters}
          />
        ) : keywordResults.length === 0 ? (
          <NoResultsState />
        ) : (
          <div className="space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-12 lg:gap-y-0 lg:border-t lg:border-page">
            {keywordResults.map((item, index) => (
              <SearchArticleCard key={item.id ?? index} article={item} subCategoryToLabel={subCategoryToLabel} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

// ── AI Search Results ──────────────────────────────────

function AISearchResults({
  results,
  insight,
  clusters,
}: {
  results: SemanticResult[]
  insight: string | null
  clusters: SearchCluster[]
}) {
  if (results.length === 0) {
    return <NoResultsState />
  }

  return (
    <div className="space-y-6">
      {/* 인사이트 카드 */}
      {insight && <InsightCard insight={insight} />}

      {/* 검색 결과 목록 */}
      <div>
        <h2 className="text-sm font-semibold text-page-secondary mb-3">검색 결과</h2>
        <div className="space-y-0">
          {results.map((item) => (
            <SemanticArticleCard key={item.news_id} result={item} />
          ))}
        </div>
      </div>

      {/* 클러스터 영역 */}
      {clusters.length > 0 && (
        <ClusterSection clusters={clusters} results={results} />
      )}
    </div>
  )
}

function InsightCard({ insight }: { insight: string }) {
  return (
    <div className="rounded-2xl bg-gradient-to-r from-primary-50 to-blue-50 dark:from-primary-900/20 dark:to-blue-900/20 border border-primary-200 dark:border-primary-800/40 p-4 md:p-5">
      <div className="flex items-start gap-3">
        <span className="flex-shrink-0 w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center mt-0.5">
          <MaterialIcon name="lightbulb" className="text-primary-500" size={18} />
        </span>
        <div>
          <p className="text-xs font-semibold text-primary-600 dark:text-primary-400 mb-1">핵심 인사이트</p>
          <p className="text-sm text-page leading-relaxed">{insight}</p>
        </div>
      </div>
    </div>
  )
}

function SemanticArticleCard({ result }: { result: SemanticResult }) {
  const detailUrl = newsDetailPath(result.news_id, '최신')
  const simPercent = Math.round(result.similarity * 100)

  const imageUrl =
    result.image_url ||
    getPlaceholderImageUrl(
      {
        id: result.news_id,
        title: result.title,
        description: result.description,
        published_at: result.published_at,
        category: result.category,
      },
      200,
      200
    )

  const formatDate = () => {
    if (!result.published_at) return ''
    const date = new Date(result.published_at)
    return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
  }

  return (
    <article className="bg-page py-4 border-b border-page last:border-b-0">
      <div className="flex items-center gap-2 mb-2">
        {result.topic_category && (
          <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
            {result.topic_category}
          </span>
        )}
        <span className="text-[11px] text-page-muted">
          유사도 {simPercent}%
        </span>
      </div>
      <div className="grid grid-cols-[1fr_auto] items-start gap-4">
        <div className="min-w-0 flex flex-col">
          <Link to={detailUrl} state={{ fromTab: '최신' }} className="flex flex-col gap-1">
            <h3 className="text-base font-bold text-page leading-snug line-clamp-2 break-keep-ko-mobile">
              {result.title}
            </h3>
            {result.topic_label && (
              <p className="text-xs text-page-muted italic">
                {result.topic_label}
              </p>
            )}
            {result.description && (
              <p className="text-xs text-page-secondary leading-relaxed line-clamp-2 break-keep-ko-mobile mt-0.5">
                {stripHtml(result.description)}
              </p>
            )}
          </Link>
        </div>
        <Link to={detailUrl} state={{ fromTab: '최신' }} className="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-page-secondary block">
          <img
            src={imageUrl}
            alt={result.title}
            className="w-full h-full object-cover"
            loading="lazy"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                { id: result.news_id, title: result.title, description: result.description, published_at: result.published_at, category: result.category },
                200, 200
              )
            }}
          />
        </Link>
      </div>
      <div className="flex items-center gap-2 mt-2 text-[11px] text-page-muted">
        {result.entities?.length > 0 && (
          <>
            <span>{result.entities.slice(0, 3).join(' · ')}</span>
            <span>|</span>
          </>
        )}
        {result.region?.length > 0 && (
          <>
            <span>{result.region.join(' · ')}</span>
            <span>|</span>
          </>
        )}
        <span>{formatDate()}</span>
      </div>
    </article>
  )
}

function ClusterSection({ clusters, results }: { clusters: SearchCluster[]; results: SemanticResult[] }) {
  return (
    <div>
      <h2 className="text-sm font-semibold text-page-secondary mb-3 flex items-center gap-1.5">
        <MaterialIcon name="auto_awesome" size={16} className="text-primary-500" />
        이런 주제는 어떠세요?
      </h2>
      <div className="space-y-3">
        {clusters.map((cluster, ci) => (
          <ClusterCard key={ci} cluster={cluster} results={results} />
        ))}
      </div>
    </div>
  )
}

function ClusterCard({ cluster, results }: { cluster: SearchCluster; results: SemanticResult[] }) {
  const [expanded, setExpanded] = useState(false)
  const [analysisText, setAnalysisText] = useState('')
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [analysisError, setAnalysisError] = useState('')
  const heroResult = results[cluster.hero_index]
  const otherIndices = cluster.article_indices.filter((i) => i !== cluster.hero_index)

  if (!heroResult) return null

  const heroDetailUrl = newsDetailPath(heroResult.news_id, '최신')

  const handleAnalysis = async () => {
    if (expanded && analysisText) {
      setExpanded(false)
      return
    }
    setExpanded(true)
    if (analysisText) return

    setIsAnalyzing(true)
    setAnalysisError('')
    setAnalysisText('')

    const newsIds = cluster.article_indices
      .map((i) => results[i]?.news_id)
      .filter((id): id is number => id != null && id > 0)

    try {
      const response = await newsApi.clusterAnalysis(newsIds, cluster.name)
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      const reader = response.body?.getReader()
      if (!reader) throw new Error('No reader')

      const decoder = new TextDecoder()
      let buffer = ''

      let readResult = await reader.read()
      while (!readResult.done) {
        const chunk = readResult.value
        if (chunk) {
          buffer += decoder.decode(chunk, { stream: true })
        }
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            try {
              const parsed = JSON.parse(line.slice(6))
              if (parsed.text) {
                setAnalysisText((prev) => prev + parsed.text)
              }
            } catch {
              // skip malformed lines
            }
          }
        }
        readResult = await reader.read()
      }
    } catch (err) {
      setAnalysisError(err instanceof Error ? err.message : '분석 중 오류가 발생했습니다.')
    } finally {
      setIsAnalyzing(false)
    }
  }

  return (
    <div className="rounded-2xl border border-page bg-page-secondary/30 p-4">
      <h3 className="text-sm font-bold text-page mb-3">{cluster.name}</h3>

      {/* Hero article */}
      <Link to={heroDetailUrl} state={{ fromTab: '최신' }} className="flex items-start gap-3 mb-2">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-page line-clamp-2">{heroResult.title}</p>
          <p className="text-[11px] text-page-muted mt-0.5">
            유사도 {Math.round(heroResult.similarity * 100)}%
          </p>
        </div>
        {heroResult.image_url && (
          <img
            src={heroResult.image_url}
            alt=""
            className="w-16 h-16 rounded-lg object-cover flex-shrink-0"
            loading="lazy"
          />
        )}
      </Link>

      {/* Other articles in cluster */}
      {otherIndices.length > 0 && (
        <div className="space-y-1 ml-1 mb-3">
          {otherIndices.map((idx) => {
            const r = results[idx]
            if (!r) return null
            return (
              <Link
                key={r.news_id}
                to={newsDetailPath(r.news_id, '최신')}
                state={{ fromTab: '최신' }}
                className="flex items-center gap-2 text-xs text-page-secondary hover:text-page transition-colors"
              >
                <span className="text-page-muted">·</span>
                <span className="line-clamp-1 flex-1">{r.title}</span>
                <span className="text-page-muted whitespace-nowrap">({Math.round(r.similarity * 100)}%)</span>
              </Link>
            )
          })}
        </div>
      )}

      {/* 종합 분석 버튼 */}
      <button
        type="button"
        onClick={handleAnalysis}
        disabled={isAnalyzing}
        className={`w-full flex items-center justify-center gap-1.5 py-2 rounded-xl text-xs font-semibold transition-colors ${
          isAnalyzing
            ? 'text-page-muted bg-page-secondary cursor-wait'
            : 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 dark:hover:bg-primary-900/30'
        }`}
      >
        {isAnalyzing ? (
          <>
            <span className="inline-block w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            분석 중...
          </>
        ) : (
          <>
            <MaterialIcon name="analytics" size={16} />
            종합 분석 보기
            <MaterialIcon name={expanded ? 'expand_less' : 'expand_more'} size={16} />
          </>
        )}
      </button>

      {/* 종합 분석 결과 */}
      {expanded && (
        <div className="mt-3 p-4 rounded-xl bg-page border border-page">
          {analysisError ? (
            <p className="text-xs text-red-500 text-center py-2">{analysisError}</p>
          ) : analysisText ? (
            <p className="text-sm text-page leading-relaxed whitespace-pre-wrap">{analysisText}</p>
          ) : isAnalyzing ? (
            <p className="text-xs text-page-muted text-center py-4">AI가 기사들을 종합 분석하고 있습니다...</p>
          ) : null}
        </div>
      )}
    </div>
  )
}

// ── Shared UI ──────────────────────────────────────────

function EmptySearchState() {
  return (
    <div className="text-center py-16 text-page-secondary">
      <p className="mb-2">상단 검색 아이콘을 눌러 검색어를 입력해 주세요.</p>
      <p className="text-sm">AI 검색은 의미 기반으로, 키워드 검색은 제목·내용에서 찾습니다.</p>
    </div>
  )
}

function NoResultsState() {
  return (
    <div className="flex flex-col items-center px-4 py-16 text-center">
      <div className="relative mb-8 flex h-36 w-36 items-center justify-center">
        <span className="absolute inset-0 rounded-full bg-page-secondary opacity-90 dark:opacity-100" aria-hidden />
        <span className="absolute -bottom-1 left-1/2 h-14 w-20 -translate-x-1/2 rounded-full bg-page shadow-md border border-page dark:bg-page-secondary" aria-hidden />
        <span className="relative z-[1] flex h-16 w-16 items-center justify-center rounded-full bg-page-secondary shadow-inner border border-page">
          <MaterialIcon name="search" className="text-page-muted" size={40} />
        </span>
      </div>
      <p className="max-w-sm text-base leading-relaxed text-page-secondary">
        죄송합니다 해당 검색어로는 검색이 되지 않습니다.
      </p>
    </div>
  )
}

// ── Keyword Search Card (기존 보존) ────────────────────

function SearchArticleCard({ article, subCategoryToLabel }: { article: NewsItem; subCategoryToLabel: Record<string, string> }) {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [isBookmarking, setIsBookmarking] = useState(false)

  const imageUrl =
    article.image_url ||
    getPlaceholderImageUrl(
      {
        id: article.id,
        title: article.title,
        description: article.description,
        published_at: article.published_at,
        category: article.category,
      },
      200,
      200
    )

  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    const dateStr = article.display_date ?? article.published_at
    if (dateStr) {
      const date = new Date(dateStr)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  const getSourceName = () => {
    let raw: string
    if (article.original_source && String(article.original_source).trim()) raw = article.original_source
    else if (article.source === 'Admin') return 'the gist.'
    else raw = article.source || 'the gist.'
    return formatSourceDisplayName(raw) || 'the gist.'
  }

  const getCategoryLabel = () => {
    if (article.category) return subCategoryToLabel[article.category] ?? article.category
    if (article.source === 'Admin') return 'the gist.'
    return formatSourceDisplayName(article.source) || 'the gist.'
  }

  const handlePlayAudio = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id
    if (!newsId) return

    addAudioItem({
      id: Number(newsId),
      title: article.title,
      description: article.description ?? null,
      source: article.source ?? null,
      category: article.category ?? null,
      published_at: article.published_at ?? null,
    })

    try {
      const res = await newsApi.getDetail(Number(newsId))
      const detail = res.data?.data
      if (detail) {
        const originalTitle = (detail.original_title && String(detail.original_title).trim()) || extractTitleFromUrl(detail.url) || '원문'
        const displayDate = (detail as { display_date?: string }).display_date ?? detail.published_at ?? detail.created_at
        const dateStr = displayDate
          ? `${new Date(displayDate).getFullYear()}년 ${new Date(displayDate).getMonth() + 1}월 ${new Date(displayDate).getDate()}일`
          : ''
        const rawSource = (detail.original_source && String(detail.original_source).trim()) || (detail.source === 'Admin' ? 'the gist.' : detail.source || 'the gist.')
        const sourceDisplay = formatSourceDisplayName(rawSource) || 'the gist.'
        const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
        const mainContent = stripHtml(detail.narration || detail.content || detail.description || article.description || '')
        const critiquePart = detail.why_important ? stripHtml(detail.why_important) : ''
        const img = detail.image_url || article.image_url || ''
        openAndPlay(detail.title, editorialLine, mainContent, critiquePart, 1.0, img, Number(newsId))
        return
      }
    } catch { /* fallback */ }

    const text = `${article.title}. ${article.description || ''}`.trim()
    if (text) {
      const url = (article as { url?: string; source_url?: string }).url || (article as { source_url?: string }).source_url
      const originalTitle = extractTitleFromUrl(url) || '원문'
      const sourceDisplay = getSourceName()
      const displayDate = article.display_date ?? article.published_at
      const dateStr = displayDate
        ? `${new Date(displayDate).getFullYear()}년 ${new Date(displayDate).getMonth() + 1}월 ${new Date(displayDate).getDate()}일`
        : ''
      const editorialLine = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
      openAndPlay(article.title, editorialLine, text, '', 1.0, undefined, Number(newsId))
    }
  }

  const shareWebUrl = `${window.location.origin}/news/${article.id}`

  const handleBookmark = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const newsId = article.id
    if (!newsId) {
      alert('이 기사는 즐겨찾기에 추가할 수 없습니다.')
      return
    }
    if (!isAuthenticated) {
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) navigate('/login', { state: { returnTo: window.location.pathname } })
      return
    }
    setIsBookmarking(true)
    try {
      if (isBookmarked) {
        await newsApi.removeBookmark(Number(newsId))
        setIsBookmarked(false)
      } else {
        await newsApi.bookmark(Number(newsId))
        setIsBookmarked(true)
      }
    } catch (err: unknown) {
      alert(apiErrorMessage(err, '즐겨찾기 처리에 실패했습니다.'))
    } finally {
      setIsBookmarking(false)
    }
  }

  const detailUrl = article.id ? newsDetailPath(article.id, '최신') : '/news/'

  return (
    <article className="bg-page py-5">
      <div className="grid grid-cols-[1fr_auto] items-start gap-4">
        <div className="min-w-0 flex flex-col">
          <Link to={detailUrl} state={{ fromTab: '최신' }} className="flex flex-col justify-center">
            <h2 className="text-lg font-bold text-page leading-snug mb-1.5 line-clamp-2 break-keep-ko-mobile">
              {article.title}
            </h2>
            {(article.narration || article.description) && (
              <p className="text-xs text-page-secondary leading-relaxed line-clamp-3 break-keep-ko-mobile">
                {stripHtml(article.narration?.trim() || article.description)}
              </p>
            )}
          </Link>
        </div>
        <Link to={detailUrl} state={{ fromTab: '최신' }} className="w-28 h-28 flex-shrink-0 rounded-none overflow-hidden bg-page-secondary block aspect-square">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover"
            loading="lazy"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                {
                  id: article.id,
                  title: article.title,
                  description: article.description,
                  published_at: article.published_at,
                  category: article.category,
                  url: article.url,
                  source: article.source,
                },
                200,
                200
              )
            }}
          />
        </Link>
      </div>
      <div className="flex items-center justify-between pt-2 mt-2 border-t border-page">
        <Link to={detailUrl} state={{ fromTab: '최신' }} className="flex items-center gap-1.5 text-xs shrink-0">
          <span className="font-medium text-primary-500">{getCategoryLabel()}</span>
          <span className="text-page-muted">|</span>
          <span className="text-page-secondary">{formatDate()}</span>
        </Link>
        <div className="flex items-center gap-2 shrink-0" role="group" aria-label="기사 액션">
          <button
            type="button"
            onClick={handlePlayAudio}
            className="p-1 transition-colors text-page-secondary hover:text-page"
            title="음성으로 듣기"
            aria-label="재생"
          >
            <MaterialIcon name="headphones" className="w-5 h-5 shrink-0" size={20} />
          </button>
          <ShareMenu
            title={article.title}
            description={article.description || ''}
            imageUrl={imageUrl}
            webUrl={shareWebUrl}
            className="text-page-secondary hover:text-page"
            titleAttr="공유하기"
            iconClassName="w-5 h-5"
          />
          <button
            type="button"
            onClick={handleBookmark}
            disabled={isBookmarking}
            className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-page-secondary hover:text-page'} ${isBookmarking ? 'opacity-60 cursor-wait' : ''}`}
            title="즐겨찾기"
            aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
          >
            {isBookmarking ? (
              <span className="inline-block w-5 h-5 shrink-0 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            ) : isBookmarked ? (
              <MaterialIcon name="bookmark" filled className="w-5 h-5 shrink-0 text-primary-500" size={20} />
            ) : (
              <MaterialIcon name="bookmark_border" className="w-5 h-5 shrink-0" size={20} />
            )}
          </button>
        </div>
      </div>
    </article>
  )
}

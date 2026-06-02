import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import MaterialIcon from '../components/Common/MaterialIcon'
import SearchAskInput from '../components/Search/SearchAskInput'
import SearchLanding from '../components/Search/SearchLanding'
import { SEARCH_ENTRY_ICON } from '../constants/site'
import { newsApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'
import { stripHtml } from '../utils/sanitizeHtml'
import { newsDetailPath } from '../utils/newsDetailLink'

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
  question: string
  article_indices: number[]
  hero_index: number
}

interface SemanticSearchResponse {
  success: boolean
  results: SemanticResult[]
  clusters: SearchCluster[]
  insight?: string | null
  meta: {
    query: string
    total: number
    filter_category: string | null
  }
}

/** useMemo 의존성 안정화용 (매 렌더마다 []를 쓰면 exhaustive-deps 경고 발생) */
const EMPTY_SEMANTIC_RESULTS: SemanticResult[] = []

export default function SearchPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const q = searchParams.get('q')?.trim() ?? ''

  const goSearch = (query: string) => {
    navigate(`/search?q=${encodeURIComponent(query)}`)
  }

  const semanticQuery = useQuery({
    queryKey: ['semanticSearch', q],
    queryFn: async () => {
      const res = await newsApi.semanticSearch(q, undefined, 20)
      return res.data as SemanticSearchResponse
    },
    enabled: q.length >= 1,
    staleTime: 1000 * 60 * 2,
  })

  const isLoading = semanticQuery.isLoading
  const isFetched = semanticQuery.isFetched
  const searched = isFetched && q.length >= 1

  const semanticData = semanticQuery.data
  const semanticResults = semanticData?.results ?? EMPTY_SEMANTIC_RESULTS
  const clusters = semanticData?.clusters ?? []
  const insight = semanticData?.insight?.trim() || null

  const resultCount = semanticResults.length

  if (!q) {
    return (
      <div className="min-h-[100dvh] flex flex-col bg-page">
        <SearchLanding logoSize="header">
          <SearchAskInput onSubmit={goSearch} autoFocus />
        </SearchLanding>
      </div>
    )
  }

  return (
    <div className="min-h-[100dvh] flex flex-col bg-page">
      <div className="flex-1 overflow-y-auto">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 pt-6 md:pt-8 pb-28">
          <div className="mb-4">
            <h1 className="text-xl md:text-2xl font-semibold text-page">
              「{q}」에 대한 결과
            </h1>
            {searched && !isLoading && (
              <p className="text-sm text-page-secondary mt-1.5 font-medium">{resultCount}건</p>
            )}
          </div>

          {isLoading ? (
            <div className="flex flex-col justify-center items-center py-20 gap-3">
              <LoadingSpinner size="large" />
              <p className="text-sm text-page-secondary">AI가 검색어를 분석 중...</p>
            </div>
          ) : (
            <AISearchResults results={semanticResults} clusters={clusters} insight={insight} />
          )}
        </div>
      </div>

      <div className="sticky bottom-0 z-10 border-t border-page bg-page/95 backdrop-blur-sm px-4 md:px-6 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto">
          <SearchAskInput onSubmit={goSearch} disabled={isLoading} />
        </div>
      </div>
    </div>
  )
}

// ── AI Search Results ──────────────────────────────────

function AISearchResults({
  results,
  clusters,
  insight,
}: {
  results: SemanticResult[]
  clusters: SearchCluster[]
  insight: string | null
}) {
  if (results.length === 0) {
    return <NoResultsState />
  }

  return (
    <div className="space-y-6">
      {insight && <SearchInsightBanner insight={insight} />}

      {clusters.length > 0 && (
        <ClusterSection clusters={clusters} results={results} />
      )}

      <div>
        <h2 className="text-sm font-semibold text-page-secondary mb-3">검색 결과</h2>
        <div className="space-y-0">
          {results.map((item) => (
            <SemanticArticleCard key={item.news_id} result={item} />
          ))}
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
      <h2 className="text-sm font-semibold text-page-secondary mb-3">이런 주제는 어떠세요?</h2>
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

  const displayLabel = cluster.question || cluster.name

  return (
    <div>
      <button
        type="button"
        onClick={handleAnalysis}
        disabled={isAnalyzing}
        className={`w-full text-left rounded-2xl border transition-all ${
          expanded
            ? 'border-primary-300 dark:border-primary-700 bg-primary-50/50 dark:bg-primary-900/10'
            : 'border-page bg-page hover:border-primary-200 dark:hover:border-primary-800 hover:bg-primary-50/30 dark:hover:bg-primary-900/10'
        } p-4`}
      >
        <div className="flex items-center justify-between gap-3">
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-page leading-snug">{displayLabel}</p>
            <span className="text-[11px] text-page-muted mt-1 block">
              기사 {cluster.article_indices.length}건 기반 분석
            </span>
          </div>
          <div className="flex-shrink-0">
            {isAnalyzing ? (
              <span className="inline-block w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            ) : (
              <MaterialIcon
                name={expanded ? 'expand_less' : 'arrow_forward'}
                size={20}
                className="text-primary-500 dark:text-primary-400"
              />
            )}
          </div>
        </div>
      </button>

      {expanded && (
        <div className="mt-2 mx-2 p-4 rounded-xl bg-page-secondary/40 border border-page">
          {analysisError ? (
            <p className="text-xs text-red-500 text-center py-2">{analysisError}</p>
          ) : analysisText ? (
            <p className="text-sm text-page leading-relaxed whitespace-pre-wrap">{analysisText}</p>
          ) : isAnalyzing ? (
            <p className="text-xs text-page-muted text-center py-4">AI가 종합 분석하고 있습니다...</p>
          ) : null}
        </div>
      )}
    </div>
  )
}

// ── Shared UI ──────────────────────────────────────────

function SearchInsightBanner({ insight }: { insight: string }) {
  return (
    <div className="rounded-2xl border border-primary-200 dark:border-primary-800 bg-primary-50/60 dark:bg-primary-900/15 px-4 py-3.5">
      <div className="flex gap-3 items-start">
        <MaterialIcon
          name="lightbulb"
          size={20}
          className="text-primary-600 dark:text-primary-400 flex-shrink-0 mt-0.5"
        />
        <div className="min-w-0">
          <p className="text-[11px] font-semibold text-primary-700 dark:text-primary-300 mb-1">
            AI 인사이트
          </p>
          <p className="text-sm text-page leading-relaxed break-keep-ko-mobile">{insight}</p>
        </div>
      </div>
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
          <MaterialIcon name={SEARCH_ENTRY_ICON} className="text-page-muted" size={40} />
        </span>
      </div>
      <p className="max-w-sm text-base leading-relaxed text-page-secondary">
        죄송합니다 해당 검색어로는 검색이 되지 않습니다.
      </p>
    </div>
  )
}

import { useQuery, useInfiniteQuery } from '@tanstack/react-query'
import { newsApi } from '../services/api'
import { queryKeys } from '../lib/queryClient'

interface NewsListParams {
  page?: number
  limit?: number
  category?: string
  status?: string
}

interface NewsDetailParams {
  from_tab?: string
  _t?: number
}

interface NewsItem {
  id?: number
  title: string
  description: string
  narration?: string | null
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
}

interface NewsListResponse {
  success: boolean
  data: {
    items: NewsItem[]
    pagination: {
      page: number
      limit: number
      total: number
      total_pages: number
    }
  }
}

export function useNewsList(params: NewsListParams = {}) {
  const { page = 1, limit = 20, category } = params
  return useQuery({
    queryKey: queryKeys.news.list({ page, limit, category }),
    queryFn: async () => {
      const response = await newsApi.getList(page, limit, category)
      return response.data
    },
  })
}

export function useInfiniteNewsList(category?: string, limit = 20) {
  return useInfiniteQuery({
    queryKey: ['news', 'infinite', category ?? 'all'],
    queryFn: async ({ pageParam = 1 }) => {
      const response = await newsApi.getList(pageParam, limit, category)
      return response.data as NewsListResponse
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      const pagination = lastPage.data?.pagination
      if (!pagination) return undefined
      return pagination.page < pagination.total_pages ? pagination.page + 1 : undefined
    },
    staleTime: 1000 * 60 * 2, // 2분 캐시
  })
}

export function useNewsDetail(id: number, params: NewsDetailParams = {}) {
  return useQuery({
    queryKey: queryKeys.news.detail(id, params as Record<string, unknown>),
    queryFn: async () => {
      const response = await newsApi.getDetail(id, params as Record<string, unknown>)
      return response.data
    },
    enabled: !!id,
  })
}

export function usePopularNews(enabled = true) {
  return useQuery({
    queryKey: queryKeys.news.popular(),
    queryFn: async () => {
      const response = await newsApi.getPopular()
      return response.data
    },
    enabled,
    staleTime: 1000 * 60 * 2, // 2분 캐시
  })
}

export function useSearchNews(query: string) {
  return useQuery({
    queryKey: queryKeys.news.search(query),
    queryFn: async () => {
      const response = await newsApi.search(query)
      return response.data
    },
    enabled: query.length >= 2,
  })
}

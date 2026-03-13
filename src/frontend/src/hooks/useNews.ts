import { useQuery } from '@tanstack/react-query'
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

export function usePopularNews() {
  return useQuery({
    queryKey: queryKeys.news.popular(),
    queryFn: async () => {
      const response = await newsApi.getPopular()
      return response.data
    },
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

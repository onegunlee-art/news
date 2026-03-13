import { QueryClient } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5분 동안 fresh
      gcTime: 1000 * 60 * 30, // 30분 동안 캐시 유지 (이전 cacheTime)
      retry: 1,
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
    },
  },
})

export const queryKeys = {
  news: {
    all: ['news'] as const,
    lists: () => [...queryKeys.news.all, 'list'] as const,
    list: (params: Record<string, unknown>) => [...queryKeys.news.lists(), params] as const,
    details: () => [...queryKeys.news.all, 'detail'] as const,
    detail: (id: number, params?: Record<string, unknown>) => [...queryKeys.news.details(), id, params] as const,
    popular: () => [...queryKeys.news.all, 'popular'] as const,
    search: (query: string) => [...queryKeys.news.all, 'search', query] as const,
  },
  bookmarks: {
    all: ['bookmarks'] as const,
    list: () => [...queryKeys.bookmarks.all, 'list'] as const,
  },
  user: {
    all: ['user'] as const,
    profile: () => [...queryKeys.user.all, 'profile'] as const,
  },
} as const

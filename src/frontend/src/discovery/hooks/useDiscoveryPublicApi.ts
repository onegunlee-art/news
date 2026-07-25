import { useCallback, useMemo, useState } from 'react'
import { discoveryDeviceHeaders } from './useDeviceKey'
import type { DiscoveryChange, DiscoveryEdition, DiscoveryPollStats } from '../types'

interface TodayResponse {
  edition: DiscoveryEdition | null
  changes: DiscoveryChange[]
  viewer: { has_voted: boolean; option_idx: number | null; poll_id: number | null }
  meta: {
    today_date: string
    today_status: string
    display_mode: string
    message?: string | null
  }
}

interface EditionListResponse {
  items: DiscoveryEdition[]
  next_cursor: string | null
  has_more: boolean
}

interface CommentsResponse {
  items: Array<{ id: number; poll_id: number; body: string; created_at: string }>
  next_cursor: string | null
  has_more: boolean
}

async function parseJson(res: Response) {
  const data = await res.json()
  if (!res.ok || !data.success) {
    throw new Error(data.error || `HTTP ${res.status}`)
  }
  return data
}

export function useDiscoveryPublicApi() {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const headers = useMemo(() => ({
    ...discoveryDeviceHeaders(),
    'Content-Type': 'application/json',
  }), [])

  const fetchToday = useCallback(async (): Promise<TodayResponse> => {
    setLoading(true)
    setError(null)
    try {
      const res = await fetch('/api/discovery/today.php', { headers: discoveryDeviceHeaders() })
      const data = await parseJson(res)
      return data.data as TodayResponse
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load today')
      throw e
    } finally {
      setLoading(false)
    }
  }, [])

  const fetchEdition = useCallback(async (date: string) => {
    setLoading(true)
    setError(null)
    try {
      const res = await fetch(`/api/discovery/edition.php?date=${encodeURIComponent(date)}`, {
        headers: discoveryDeviceHeaders(),
      })
      const data = await parseJson(res)
      return data.data as { edition: DiscoveryEdition; changes: DiscoveryChange[]; viewer: TodayResponse['viewer'] }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load edition')
      throw e
    } finally {
      setLoading(false)
    }
  }, [])

  const listEditions = useCallback(async (cursor?: string | null, limit = 30): Promise<EditionListResponse> => {
    const qs = new URLSearchParams({ limit: String(limit) })
    if (cursor) qs.set('cursor', cursor)
    const res = await fetch(`/api/discovery/editions.php?${qs}`, { headers: discoveryDeviceHeaders() })
    const data = await parseJson(res)
    return data.data as EditionListResponse
  }, [])

  const fetchPoll = useCallback(async (pollId: number) => {
    const res = await fetch(`/api/discovery/poll.php?id=${pollId}`, { headers: discoveryDeviceHeaders() })
    const data = await parseJson(res)
    return data.data as {
      poll: { id: number; stats: DiscoveryPollStats; question: string; options: string[] }
      change: DiscoveryChange
      edition: { edition_date: string }
      viewer: { has_voted: boolean; option_idx: number | null; poll_id: number }
    }
  }, [])

  const fetchChange = useCallback(async (changeId: number) => {
    const res = await fetch(`/api/discovery/change.php?id=${changeId}`, { headers: discoveryDeviceHeaders() })
    const data = await parseJson(res)
    return data.data
  }, [])

  const vote = useCallback(async (pollId: number, optionIdx: number) => {
    const res = await fetch('/api/discovery/vote.php', {
      method: 'POST',
      headers,
      body: JSON.stringify({ poll_id: pollId, option_idx: optionIdx }),
    })
    return parseJson(res)
  }, [headers])

  const fetchComments = useCallback(async (pollId: number, cursor?: string | null): Promise<CommentsResponse> => {
    const qs = new URLSearchParams({ poll_id: String(pollId), limit: '20' })
    if (cursor) qs.set('cursor', cursor)
    const res = await fetch(`/api/discovery/comments.php?${qs}`, { headers: discoveryDeviceHeaders() })
    const data = await parseJson(res)
    return data.data as CommentsResponse
  }, [])

  const postComment = useCallback(async (pollId: number, body: string) => {
    const res = await fetch('/api/discovery/comment.php', {
      method: 'POST',
      headers,
      body: JSON.stringify({ poll_id: pollId, body }),
    })
    return parseJson(res)
  }, [headers])

  const fetchMyStats = useCallback(async () => {
    const res = await fetch('/api/discovery/me.php', { headers: discoveryDeviceHeaders() })
    const data = await parseJson(res)
    return data.data as { votes_count: number; comments_count: number }
  }, [])

  const search = useCallback(async (q: string) => {
    const res = await fetch(`/api/discovery/search.php?q=${encodeURIComponent(q)}`, {
      headers: discoveryDeviceHeaders(),
    })
    const data = await parseJson(res)
    return data.data.results as DiscoveryChange[]
  }, [])

  return {
    loading,
    error,
    fetchToday,
    fetchEdition,
    listEditions,
    fetchPoll,
    fetchChange,
    vote,
    fetchComments,
    postComment,
    fetchMyStats,
    search,
  }
}

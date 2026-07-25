import { useCallback, useState } from 'react'
import { adminFetch } from '../../../../services/api'
import type { DiscoveryChange, DiscoveryEdition, DiscoveryRun } from '../types'

async function parseJson(res: Response) {
  const data = await res.json()
  if (!res.ok || !data.success) {
    throw new Error(data.error || `HTTP ${res.status}`)
  }
  return data
}

export function useDiscoveryApi() {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const listEditions = useCallback(async (): Promise<{ editions: DiscoveryEdition[]; runs: DiscoveryRun[] }> => {
    setLoading(true)
    setError(null)
    try {
      const res = await adminFetch('/api/admin/discovery/editions.php')
      const data = await parseJson(res)
      return data.data
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load editions')
      throw e
    } finally {
      setLoading(false)
    }
  }, [])

  const fetchPreview = useCallback(async (date: string): Promise<{ edition: DiscoveryEdition | null; changes: DiscoveryChange[]; editions: DiscoveryEdition[] }> => {
    setLoading(true)
    setError(null)
    try {
      const res = await adminFetch(`/api/admin/discovery/preview.php?date=${encodeURIComponent(date)}`)
      const data = await parseJson(res)
      return data.data
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load preview')
      throw e
    } finally {
      setLoading(false)
    }
  }, [])

  const generateEdition = useCallback(async (date?: string) => {
    setLoading(true)
    setError(null)
    try {
      const res = await adminFetch('/api/admin/discovery/editions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'generate', date: date || undefined }),
      })
      return await parseJson(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Generate failed')
      throw e
    } finally {
      setLoading(false)
    }
  }, [])

  const publishEdition = useCallback(async (editionId: number) => {
    const res = await adminFetch('/api/admin/discovery/publish.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ edition_id: editionId }),
    })
    return parseJson(res)
  }, [])

  const updateChange = useCallback(async (payload: Record<string, unknown>) => {
    const res = await adminFetch('/api/admin/discovery/changes.php', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    return parseJson(res)
  }, [])

  const deleteChange = useCallback(async (id: number) => {
    const res = await adminFetch('/api/admin/discovery/changes.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    })
    return parseJson(res)
  }, [])

  const vote = useCallback(async (pollId: number, optionIdx: number) => {
    const res = await adminFetch('/api/admin/discovery/votes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ poll_id: pollId, option_idx: optionIdx, user_key: 'admin-preview' }),
    })
    return parseJson(res)
  }, [])

  const search = useCallback(async (q: string, days = 30) => {
    const res = await adminFetch(`/api/admin/discovery/preview.php?q=${encodeURIComponent(q)}&days=${days}`)
    const data = await parseJson(res)
    return data.data.results as DiscoveryChange[]
  }, [])

  const deleteComment = useCallback(async (id: number) => {
    const res = await adminFetch('/api/admin/discovery/comments.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    })
    return parseJson(res)
  }, [])

  return {
    loading,
    error,
    listEditions,
    fetchPreview,
    generateEdition,
    publishEdition,
    updateChange,
    deleteChange,
    vote,
    search,
    deleteComment,
  }
}

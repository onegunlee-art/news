import { useEffect, useRef, useCallback } from 'react'

const INTERVAL_MS = 5 * 60 * 1000 // 5분

export function useVersionCheck() {
  const baseVersion = useRef<number | null>(null)

  const fetchVersion = useCallback(async (): Promise<number | null> => {
    try {
      const res = await fetch(`/version.json?t=${Date.now()}`, {
        cache: 'no-store',
      })
      if (!res.ok) return null
      const data = await res.json()
      return typeof data.v === 'number' ? data.v : null
    } catch {
      return null
    }
  }, [])

  const checkAndReload = useCallback(async () => {
    const latest = await fetchVersion()
    if (latest === null || baseVersion.current === null) return
    if (latest !== baseVersion.current) {
      window.location.reload()
    }
  }, [fetchVersion])

  useEffect(() => {
    fetchVersion().then((v) => {
      if (v !== null) baseVersion.current = v
    })

    const id = setInterval(checkAndReload, INTERVAL_MS)

    function onVisibilityChange() {
      if (document.visibilityState === 'visible') {
        checkAndReload()
      }
    }
    document.addEventListener('visibilitychange', onVisibilityChange)

    return () => {
      clearInterval(id)
      document.removeEventListener('visibilitychange', onVisibilityChange)
    }
  }, [fetchVersion, checkAndReload])
}

import { useEffect, useRef } from 'react'

const INTERVAL_MS = 5 * 60 * 1000 // 5분

export function useVersionCheck() {
  const baseVersion = useRef<number | null>(null)

  useEffect(() => {
    async function fetchVersion(): Promise<number | null> {
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
    }

    fetchVersion().then((v) => {
      if (v !== null) baseVersion.current = v
    })

    const id = setInterval(async () => {
      const latest = await fetchVersion()
      if (latest === null || baseVersion.current === null) return
      if (latest !== baseVersion.current) {
        // 새 버전 감지 → 자동 reload
        window.location.reload()
      }
    }, INTERVAL_MS)

    return () => clearInterval(id)
  }, [])
}

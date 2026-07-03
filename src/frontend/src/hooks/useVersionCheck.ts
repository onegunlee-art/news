import { useEffect, useRef, useCallback } from 'react'

const INTERVAL_MS = 60 * 1000 // 1분 (배포 직후 모바일 PWA 반영)

declare const __APP_BUILD_VERSION__: number

const BUNDLE_VERSION =
  typeof __APP_BUILD_VERSION__ === 'number' && Number.isFinite(__APP_BUILD_VERSION__)
    ? __APP_BUILD_VERSION__
    : null

async function purgeGistAssetCaches(): Promise<void> {
  if (!('caches' in window)) return
  try {
    const keys = await caches.keys()
    await Promise.all(
      keys.filter((k) => k.startsWith('gist-assets-')).map((k) => caches.delete(k)),
    )
  } catch {
    // reload proceeds even if purge fails
  }
}

export function useVersionCheck() {
  const baseVersion = useRef<number | null>(null)
  const isReloading = useRef(false)

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

  const reloadForUpdate = useCallback(async () => {
    if (isReloading.current) return
    isReloading.current = true
    await purgeGistAssetCaches()
    window.location.reload()
  }, [])

  const checkAndReload = useCallback(async () => {
    if (isReloading.current) return
    const latest = await fetchVersion()
    if (latest === null) return

    if (BUNDLE_VERSION !== null && latest !== BUNDLE_VERSION) {
      await reloadForUpdate()
      return
    }

    if (baseVersion.current !== null && latest !== baseVersion.current) {
      await reloadForUpdate()
      return
    }

    baseVersion.current = latest
  }, [fetchVersion, reloadForUpdate])

  useEffect(() => {
    void checkAndReload()

    const id = setInterval(() => {
      void checkAndReload()
    }, INTERVAL_MS)

    function onVisibilityChange() {
      if (document.visibilityState === 'visible') {
        void checkAndReload()
      }
    }
    document.addEventListener('visibilitychange', onVisibilityChange)

    return () => {
      clearInterval(id)
      document.removeEventListener('visibilitychange', onVisibilityChange)
    }
  }, [checkAndReload])

  useEffect(() => {
    if (!('serviceWorker' in navigator)) return

    let refreshing = false
    const onControllerChange = () => {
      if (refreshing) return
      refreshing = true
      window.location.reload()
    }
    navigator.serviceWorker.addEventListener('controllerchange', onControllerChange)

    const pingUpdate = () => {
      navigator.serviceWorker.getRegistration().then(reg => reg?.update()).catch(() => {})
    }
    pingUpdate()
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') pingUpdate()
    })

    return () => {
      navigator.serviceWorker.removeEventListener('controllerchange', onControllerChange)
    }
  }, [])
}

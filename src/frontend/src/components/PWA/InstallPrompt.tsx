import { useState, useEffect, useCallback } from 'react'
import MaterialIcon from '../Common/MaterialIcon'

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const DISMISS_KEY = 'pwa_install_dismissed'

function getTodayYYYYMMDD(): string {
  return new Date().toISOString().slice(0, 10)
}

function isIOS(): boolean {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) && !('MSStream' in window)
}

function isInStandaloneMode(): boolean {
  return window.matchMedia('(display-mode: standalone)').matches || ('standalone' in navigator && (navigator as { standalone?: boolean }).standalone === true)
}

export default function InstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null)
  const [showBanner, setShowBanner] = useState(false)
  const [showIOSGuide, setShowIOSGuide] = useState(false)

  useEffect(() => {
    if (isInStandaloneMode()) return

    const dismissed = localStorage.getItem(DISMISS_KEY)
    if (dismissed === getTodayYYYYMMDD()) return

    if (isIOS()) {
      setShowIOSGuide(true)
      return
    }

    const handler = (e: Event) => {
      e.preventDefault()
      setDeferredPrompt(e as BeforeInstallPromptEvent)
      setShowBanner(true)
    }

    window.addEventListener('beforeinstallprompt', handler)
    return () => window.removeEventListener('beforeinstallprompt', handler)
  }, [])

  const handleInstall = useCallback(async () => {
    if (!deferredPrompt) return
    await deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    if (outcome === 'accepted') {
      setShowBanner(false)
    }
    setDeferredPrompt(null)
  }, [deferredPrompt])

  const handleDismiss = useCallback(() => {
    localStorage.setItem(DISMISS_KEY, getTodayYYYYMMDD())
    setShowBanner(false)
    setShowIOSGuide(false)
  }, [])

  if (!showBanner && !showIOSGuide) return null

  return (
    <div className="fixed bottom-0 inset-x-0 z-50 p-4 pb-safe">
      <div className="max-w-lg mx-auto bg-page border border-page rounded-xl shadow-lg p-4 flex items-center gap-3">
        <div className="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-lg flex items-center justify-center">
          <MaterialIcon name="home" className="text-white" size={24} />
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-page text-sm font-medium">The Gist 앱 설치</p>
          {showIOSGuide ? (
            <p className="text-page-secondary text-xs mt-0.5">
              <MaterialIcon name="ios_share" size={14} className="inline-block align-text-bottom mr-0.5" />
              공유 → "홈 화면에 추가"를 눌러주세요
            </p>
          ) : (
            <p className="text-page-secondary text-xs mt-0.5">홈 화면에 추가하고 앱처럼 사용하세요</p>
          )}
        </div>
        <div className="flex items-center gap-2 flex-shrink-0">
          {!showIOSGuide && (
            <button
              type="button"
              onClick={handleInstall}
              className="px-3 py-1.5 bg-primary-500 text-white text-xs font-medium rounded-lg hover:bg-primary-600 transition-colors"
            >
              설치
            </button>
          )}
          <button
            type="button"
            onClick={handleDismiss}
            className="p-1 text-page-secondary hover:text-page transition-colors"
            aria-label="닫기"
          >
            <MaterialIcon name="close" size={20} />
          </button>
        </div>
      </div>
    </div>
  )
}

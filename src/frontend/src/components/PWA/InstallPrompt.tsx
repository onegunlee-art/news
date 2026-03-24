import { useState, useEffect, useCallback } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import MaterialIcon from '../Common/MaterialIcon'
import GistLogo from '../Common/GistLogo'

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const KEY_COMPLETED = 'pwa_install_completed'
const KEY_NEVER = 'pwa_install_never_prompt'
const KEY_SNOOZE_DAY = 'pwa_install_snooze_day'
/** @deprecated 호환용 — 예전 하루 스누즈 */
const LEGACY_DISMISS_KEY = 'pwa_install_dismissed'

const URL_ACK_PARAM = 'gist_pwa_ack'

function getTodayYYYYMMDD(): string {
  return new Date().toISOString().slice(0, 10)
}

function isIOS(): boolean {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) && !('MSStream' in window)
}

/** iOS Safari (인앱 크롬 등 제외) */
function isIOSSafari(): boolean {
  if (!isIOS()) return false
  const ua = navigator.userAgent
  if (/CriOS|FxiOS|EdgiOS|OPiOS|OPT\/|Line\/|KAKAOTALK/i.test(ua)) return false
  return /Safari/i.test(ua) && !/CriOS|FxiOS/i.test(ua)
}

function isInStandaloneMode(): boolean {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    ('standalone' in navigator && (navigator as { standalone?: boolean }).standalone === true)
  )
}

function readUrlAck(): void {
  try {
    const params = new URLSearchParams(window.location.search)
    if (params.get(URL_ACK_PARAM) !== '1') return
    localStorage.setItem(KEY_COMPLETED, '1')
    params.delete(URL_ACK_PARAM)
    const q = params.toString()
    window.history.replaceState(
      {},
      '',
      `${window.location.pathname}${q ? `?${q}` : ''}${window.location.hash}`,
    )
  } catch {
    /* ignore */
  }
}

function isSnoozedToday(): boolean {
  const today = getTodayYYYYMMDD()
  try {
    if (localStorage.getItem(KEY_SNOOZE_DAY) === today) return true
    if (localStorage.getItem(LEGACY_DISMISS_KEY) === today) return true
  } catch {
    /* ignore */
  }
  return false
}

function isInstallCompleted(): boolean {
  try {
    return localStorage.getItem(KEY_COMPLETED) === '1'
  } catch {
    return false
  }
}

function isNeverPrompt(): boolean {
  try {
    return localStorage.getItem(KEY_NEVER) === '1'
  } catch {
    return false
  }
}

function shouldShowInstallUI(): boolean {
  if (isInStandaloneMode()) return false
  if (isInstallCompleted()) return false
  if (isNeverPrompt()) return false
  if (isSnoozedToday()) return false
  return true
}

export default function InstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null)
  const [showBanner, setShowBanner] = useState(false)
  const [showIOSGuide, setShowIOSGuide] = useState(false)
  const [iosDetailOpen, setIosDetailOpen] = useState(false)

  const markCompleted = useCallback(() => {
    try {
      localStorage.setItem(KEY_COMPLETED, '1')
    } catch {
      /* ignore */
    }
    setShowBanner(false)
    setShowIOSGuide(false)
    setIosDetailOpen(false)
  }, [])

  const snoozeToday = useCallback(() => {
    try {
      localStorage.setItem(KEY_SNOOZE_DAY, getTodayYYYYMMDD())
      localStorage.removeItem(LEGACY_DISMISS_KEY)
    } catch {
      /* ignore */
    }
    setShowBanner(false)
    setShowIOSGuide(false)
    setIosDetailOpen(false)
  }, [])

  const neverAgain = useCallback(() => {
    try {
      localStorage.setItem(KEY_NEVER, '1')
      localStorage.removeItem(LEGACY_DISMISS_KEY)
    } catch {
      /* ignore */
    }
    setShowBanner(false)
    setShowIOSGuide(false)
    setIosDetailOpen(false)
  }, [])

  useEffect(() => {
    readUrlAck()

    if (isInStandaloneMode()) return

    if (!shouldShowInstallUI()) return

    const onAppInstalled = () => {
      markCompleted()
    }
    window.addEventListener('appinstalled', onAppInstalled)

    if (isIOS()) {
      setShowIOSGuide(true)
      return () => {
        window.removeEventListener('appinstalled', onAppInstalled)
      }
    }

    const handler = (e: Event) => {
      e.preventDefault()
      setDeferredPrompt(e as BeforeInstallPromptEvent)
      setShowBanner(true)
    }
    window.addEventListener('beforeinstallprompt', handler)
    return () => {
      window.removeEventListener('beforeinstallprompt', handler)
      window.removeEventListener('appinstalled', onAppInstalled)
    }
  }, [markCompleted])

  const handleInstall = useCallback(async () => {
    if (!deferredPrompt) return
    await deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    if (outcome === 'accepted') {
      markCompleted()
    }
    setDeferredPrompt(null)
  }, [deferredPrompt, markCompleted])

  if (!showBanner && !showIOSGuide) return null

  return (
    <>
      <div className="fixed bottom-0 inset-x-0 z-50 p-4 pb-safe">
        <div className="max-w-lg mx-auto bg-page border border-page rounded-xl shadow-lg p-4 flex flex-col gap-3">
          <div className="flex items-start gap-3">
            <div className="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-lg flex items-center justify-center">
              <MaterialIcon name="home" className="text-white" size={24} />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-page text-sm font-medium">
                <GistLogo as="span" size="inline" link={false} /> 앱 설치
              </p>
              {showIOSGuide ? (
                <>
                  {isIOSSafari() ? (
                    <p className="text-page-secondary text-xs mt-0.5 leading-relaxed">
                      Safari에서 홈 화면에 추가하면 앱처럼 빠르게 이용할 수 있어요.
                    </p>
                  ) : (
                    <p className="text-page-secondary text-xs mt-0.5 leading-relaxed">
                      iPhone/iPad에서는 <strong className="text-page">Safari</strong>로 이 페이지를 연 뒤, 아래
                      &quot;설치 방법&quot;을 따라주세요. (다른 브라우저에서는 홈 화면 추가가 제한될 수 있어요.)
                    </p>
                  )}
                </>
              ) : (
                <p className="text-page-secondary text-xs mt-0.5">
                  홈 화면에 추가하고 앱처럼 사용하세요
                </p>
              )}
            </div>
            <button
              type="button"
              onClick={snoozeToday}
              className="p-1 text-page-secondary hover:text-page transition-colors flex-shrink-0"
              aria-label="닫기"
            >
              <MaterialIcon name="close" size={20} />
            </button>
          </div>

          {showIOSGuide && isIOSSafari() && (
            <div className="flex flex-wrap gap-2 justify-end border-t border-page pt-3">
              <button
                type="button"
                onClick={() => setIosDetailOpen(true)}
                className="px-3 py-1.5 border border-page text-page text-xs font-medium rounded-lg hover:bg-page-secondary/10 transition-colors"
              >
                설치 방법
              </button>
              <button
                type="button"
                onClick={markCompleted}
                className="px-3 py-1.5 bg-primary-500 text-white text-xs font-medium rounded-lg hover:bg-primary-600 transition-colors"
              >
                홈 화면에 추가했어요
              </button>
            </div>
          )}

          {showIOSGuide && !isIOSSafari() && (
            <div className="flex flex-wrap gap-2 justify-end border-t border-page pt-3">
              <button
                type="button"
                onClick={() => setIosDetailOpen(true)}
                className="px-3 py-1.5 border border-page text-page text-xs font-medium rounded-lg hover:bg-page-secondary/10 transition-colors"
              >
                Safari 안내
              </button>
              <button
                type="button"
                onClick={markCompleted}
                className="px-3 py-1.5 bg-primary-500 text-white text-xs font-medium rounded-lg hover:bg-primary-600 transition-colors"
              >
                이미 추가했어요
              </button>
            </div>
          )}

          {!showIOSGuide && (
            <div className="flex flex-wrap gap-2 justify-end items-center border-t border-page pt-3">
              <button
                type="button"
                onClick={snoozeToday}
                className="px-2 py-1 text-page-secondary text-xs hover:text-page transition-colors"
              >
                오늘 하루 안 보기
              </button>
              <button
                type="button"
                onClick={neverAgain}
                className="px-2 py-1 text-page-secondary text-xs hover:text-page transition-colors"
              >
                다시 보지 않기
              </button>
              <button
                type="button"
                onClick={handleInstall}
                className="px-3 py-1.5 bg-primary-500 text-white text-xs font-medium rounded-lg hover:bg-primary-600 transition-colors"
              >
                설치
              </button>
            </div>
          )}

          {showIOSGuide && (
            <div className="flex flex-wrap gap-2 justify-between items-center text-[11px] text-page-secondary border-t border-page pt-2">
              <button type="button" onClick={snoozeToday} className="underline-offset-2 hover:underline">
                오늘 하루 안 보기
              </button>
              <button type="button" onClick={neverAgain} className="underline-offset-2 hover:underline">
                다시 보지 않기
              </button>
            </div>
          )}
        </div>
      </div>

      <IOSInstallGuideModal
        isOpen={iosDetailOpen}
        onClose={() => setIosDetailOpen(false)}
        safari={isIOSSafari()}
      />
    </>
  )
}

function IOSInstallGuideModal({
  isOpen,
  onClose,
  safari,
}: {
  isOpen: boolean
  onClose: () => void
  safari: boolean
}) {
  const steps = [
    {
      src: '/ios-install-step-1.svg',
      caption: 'Safari 하단 중앙의 공유(□↑) 버튼을 누릅니다.',
    },
    {
      src: '/ios-install-step-2.svg',
      caption: '메뉴를 아래로 스크롤한 뒤 「홈 화면에 추가」를 선택합니다.',
    },
    {
      src: '/ios-install-step-3.svg',
      caption: '이름을 확인하고 「추가」를 누른 뒤, 홈 화면 아이콘으로 실행하세요.',
    },
  ]

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/50 z-[60] backdrop-blur-sm"
            aria-hidden
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.96, y: 16 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: 16 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-x-4 bottom-8 top-[15%] md:top-[10%] z-[61] flex items-center justify-center pointer-events-none"
          >
            <div
              className="pointer-events-auto bg-page border border-page rounded-xl shadow-2xl w-full max-w-md max-h-full flex flex-col overflow-hidden text-page"
              onClick={(e) => e.stopPropagation()}
              role="dialog"
              aria-modal="true"
              aria-labelledby="ios-install-guide-title"
            >
              <div className="flex items-center justify-between px-4 py-3 border-b border-page">
                <h3 id="ios-install-guide-title" className="text-sm font-semibold">
                  {safari ? 'iPhone · Safari 설치 방법' : 'Safari에서 열기'}
                </h3>
                <button
                  type="button"
                  onClick={onClose}
                  className="p-2 text-page-secondary hover:text-page rounded-lg transition-colors"
                  aria-label="닫기"
                >
                  <MaterialIcon name="close" size={20} />
                </button>
              </div>
              <div className="overflow-y-auto px-4 py-3 space-y-4 text-xs text-page-secondary">
                {safari ? (
                  <>
                    <ol className="list-decimal list-inside space-y-4">
                      {steps.map((s, i) => (
                        <li key={s.src} className="leading-relaxed">
                          <span className="text-page font-medium">{i + 1}. </span>
                          {s.caption}
                          <div className="mt-2 rounded-lg border border-page overflow-hidden text-page bg-page-secondary/20">
                            <img
                              src={s.src}
                              alt=""
                              className="w-full h-auto block"
                              loading="lazy"
                            />
                          </div>
                        </li>
                      ))}
                    </ol>
                    <p className="flex items-start gap-1.5 text-[11px] opacity-90">
                      <MaterialIcon name="ios_share" size={16} className="flex-shrink-0 mt-0.5" />
                      iOS 버전에 따라 메뉴 이름이 조금 다를 수 있어요.
                    </p>
                  </>
                ) : (
                  <div className="space-y-3 leading-relaxed">
                    <p>
                      iOS에서 Chrome 등 다른 브라우저로 보고 계시다면,{' '}
                      <strong className="text-page">Safari</strong>로 같은 주소를 연 뒤 위 절차를 진행해 주세요.
                    </p>
                    <p>
                      Safari 주소창 옆의 <strong className="text-page">aA</strong> 또는 공유 메뉴에서
                      &quot;Safari에서 열기&quot;가 보이면 그걸 이용할 수 있어요.
                    </p>
                  </div>
                )}
              </div>
              <div className="px-4 py-3 border-t border-page">
                <button
                  type="button"
                  onClick={onClose}
                  className="w-full py-2.5 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg transition-colors"
                >
                  확인
                </button>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}

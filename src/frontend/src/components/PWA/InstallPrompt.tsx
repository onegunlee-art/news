import { useState, useEffect, useCallback, useRef } from 'react'
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
const URL_RESET_PARAM = 'gist_pwa_reset'

function getTodayYYYYMMDD(): string {
  return new Date().toISOString().slice(0, 10)
}

function isIOS(): boolean {
  if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !('MSStream' in window)) return true
  // iPadOS 13+ Safari 데스크톱 요청 시 Mac으로 보고하는 경우
  if (typeof navigator !== 'undefined' && navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) {
    return true
  }
  return false
}

function isAndroid(): boolean {
  return /Android/i.test(navigator.userAgent)
}

/** Chrome/Edge/Opera 계열 데스크톱 (설치 안내 폴백용) */
function isChromiumDesktop(): boolean {
  if (isIOS() || isAndroid()) return false
  const ua = navigator.userAgent
  return /Chrome|Chromium|Edg\//i.test(ua)
}

function isMacSafari(): boolean {
  if (isIOS() || isAndroid()) return false
  const ua = navigator.userAgent
  return /Safari/i.test(ua) && !/Chrome|Chromium|Edg|OPR|Firefox/i.test(ua)
}

type ManualInstallKind = 'android' | 'chrome_desktop' | 'safari_mac' | 'generic'

function getManualInstallKind(): ManualInstallKind {
  if (isAndroid()) return 'android'
  if (isChromiumDesktop()) return 'chrome_desktop'
  if (isMacSafari()) return 'safari_mac'
  return 'generic'
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

/** 테스트·지원용: 설치 프롬프트 관련 localStorage 초기화 */
function readUrlReset(): void {
  try {
    const params = new URLSearchParams(window.location.search)
    if (params.get(URL_RESET_PARAM) !== '1') return
    localStorage.removeItem(KEY_COMPLETED)
    localStorage.removeItem(KEY_NEVER)
    localStorage.removeItem(KEY_SNOOZE_DAY)
    localStorage.removeItem(LEGACY_DISMISS_KEY)
    params.delete(URL_RESET_PARAM)
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
  const [manualFallback, setManualFallback] = useState<ManualInstallKind | null>(null)
  const [manualDetailOpen, setManualDetailOpen] = useState(false)
  const bipSeenRef = useRef(false)

  const markCompleted = useCallback(() => {
    try {
      localStorage.setItem(KEY_COMPLETED, '1')
    } catch {
      /* ignore */
    }
    setShowBanner(false)
    setShowIOSGuide(false)
    setIosDetailOpen(false)
    setManualFallback(null)
    setManualDetailOpen(false)
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
    setManualFallback(null)
    setManualDetailOpen(false)
  }, [])

  useEffect(() => {
    readUrlAck()
    readUrlReset()

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

    bipSeenRef.current = false
    const handler = (e: Event) => {
      e.preventDefault()
      bipSeenRef.current = true
      setManualFallback(null)
      setDeferredPrompt(e as BeforeInstallPromptEvent)
      setShowBanner(true)
    }
    window.addEventListener('beforeinstallprompt', handler)

    const t = window.setTimeout(() => {
      if (!bipSeenRef.current) {
        setManualFallback(getManualInstallKind())
      }
    }, 2500)

    return () => {
      window.clearTimeout(t)
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

  const showManualChrome = manualFallback !== null

  if (!showBanner && !showIOSGuide && !showManualChrome) return null

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
              ) : showManualChrome ? (
                <p className="text-page-secondary text-xs mt-0.5 leading-relaxed">
                  {manualFallback === 'android' && (
                    <>
                      Chrome에서 <strong className="text-page">주소창 오른쪽 설치 아이콘</strong>이 보이면 눌러 설치할 수
                      있어요. 안 보이면 메뉴(⋮)에서 「앱 설치」 또는 「홈 화면에 추가」를 선택해 보세요.
                    </>
                  )}
                  {manualFallback === 'chrome_desktop' && (
                    <>
                      Chrome에서 이 사이트를 앱처럼 쓰려면 주소창의{' '}
                      <strong className="text-page">설치(모니터+화살표 아이콘)</strong>이 있을 때 누르거나, 우측 상단
                      메뉴(⋮) → 「The Gist 설치」를 사용하세요.
                    </>
                  )}
                  {manualFallback === 'safari_mac' && (
                    <>
                      Safari에서 <strong className="text-page">파일 → Dock에 추가</strong> 또는 공유 메뉴로 홈 화면
                      / Dock에서 바로 열 수 있어요. (macOS·Safari 버전에 따라 메뉴 이름이 다를 수 있어요.)
                    </>
                  )}
                  {manualFallback === 'generic' && (
                    <>
                      브라우저 메뉴에서 이 사이트를 <strong className="text-page">홈 화면 또는 앱으로 추가</strong>하는
                      항목이 있는지 확인해 보세요.
                    </>
                  )}
                </p>
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

          {showManualChrome && (
            <div className="flex flex-wrap gap-2 justify-end border-t border-page pt-3">
              <button
                type="button"
                onClick={() => setManualDetailOpen(true)}
                className="px-3 py-1.5 border border-page text-page text-xs font-medium rounded-lg hover:bg-page-secondary/10 transition-colors"
              >
                설치 방법
              </button>
              <button
                type="button"
                onClick={markCompleted}
                className="px-3 py-1.5 bg-primary-500 text-white text-xs font-medium rounded-lg hover:bg-primary-600 transition-colors"
              >
                설치했어요
              </button>
            </div>
          )}

          {!showIOSGuide && !showManualChrome && (
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

          {showManualChrome && (
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
      <ChromeManualInstallModal
        isOpen={manualDetailOpen}
        onClose={() => setManualDetailOpen(false)}
        kind={manualFallback}
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

function ChromeManualInstallModal({
  isOpen,
  onClose,
  kind,
}: {
  isOpen: boolean
  onClose: () => void
  kind: ManualInstallKind | null
}) {
  if (!kind) return null

  const title =
    kind === 'android'
      ? 'Android · Chrome'
      : kind === 'chrome_desktop'
        ? 'Chrome(데스크톱)'
        : kind === 'safari_mac'
          ? 'Mac · Safari'
          : '브라우저별 안내'

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
              aria-labelledby="chrome-manual-install-title"
            >
              <div className="flex items-center justify-between px-4 py-3 border-b border-page">
                <h3 id="chrome-manual-install-title" className="text-sm font-semibold">
                  {title}
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
              <div className="overflow-y-auto px-4 py-3 space-y-3 text-xs text-page-secondary leading-relaxed">
                {kind === 'android' && (
                  <ul className="list-disc list-inside space-y-2">
                    <li>주소창 오른쪽에 설치 아이콘이 보이면 탭하여 설치합니다.</li>
                    <li>
                      없으면 우측 상단 <MaterialIcon name="menu" size={14} className="inline align-text-bottom" /> 메뉴
                      → 「앱 설치」 또는 「홈 화면에 추가」를 찾습니다.
                    </li>
                    <li>메뉴 이름은 Chrome·Android 버전에 따라 다를 수 있습니다.</li>
                  </ul>
                )}
                {kind === 'chrome_desktop' && (
                  <ul className="list-disc list-inside space-y-2">
                    <li>주소창 오른쪽에 컴퓨터+화살표 모양(설치)이 보이면 클릭합니다.</li>
                    <li>없으면 Chrome 메뉴(⋮) → 「The Gist 설치」 또는 유사한 항목을 확인합니다.</li>
                    <li>사이트가 설치 가능 조건을 충족할 때만 버튼이 나타날 수 있습니다.</li>
                  </ul>
                )}
                {kind === 'safari_mac' && (
                  <ul className="list-disc list-inside space-y-2">
                    <li>상단 메뉴 「파일」→「Dock에 추가」또는 공유 아이콘에서 추가를 시도해 보세요.</li>
                    <li>macOS 및 Safari 버전에 따라 「웹 앱」관련 메뉴로 표시될 수 있습니다.</li>
                  </ul>
                )}
                {kind === 'generic' && (
                  <p>
                    사용 중인 브라우저의 메뉴(⋮ 또는 ≡)에서 이 사이트를 즐겨찾기·홈 화면·앱으로 추가하는 옵션을 찾아
                    보세요.
                  </p>
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

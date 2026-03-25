import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore, type AudioListItem } from '../store/audioListStore'
import { useViewSettingsStore } from '../store/viewSettingsStore'
import { newsApi, contactApi, subscriptionApi, siteSettingsApi, type SubscriptionDetail } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { formatSourceDisplayName } from '../utils/formatSource'
import MaterialIcon from '../components/Common/MaterialIcon'
import { useMenuConfig } from '../hooks/useMenuConfig'
import { apiErrorMessage } from '../utils/apiErrorMessage'

type BookmarkRow = {
  id: number
  title: string
  category?: string | null
  source?: string | null
  published_at?: string | null
}

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

const formatDateKorean = (date: Date | string | null | undefined): string => {
  if (!date) return ''
  const d = new Date(date)
  return `${d.getFullYear()}년 ${d.getMonth() + 1}월 ${d.getDate()}일`
}

export default function ProfilePage() {
  const { subCategoryToLabel } = useMenuConfig()
  const { user, isAuthenticated, isSubscribed, logout } = useAuthStore()
  const { theme, setTheme } = useViewSettingsStore()
  const hasAuth = isAuthenticated
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<'bookmarks' | 'audio' | null>(null)
  const audioItems = useAudioListStore((s) => s.items)
  const [bookmarks, setBookmarks] = useState<BookmarkRow[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [expandedActivity, setExpandedActivity] = useState<'none' | 'contact'>('none')
  const [showWithdrawConfirm, setShowWithdrawConfirm] = useState(false)
  const [withdrawing, setWithdrawing] = useState(false)
  const [showNoSubscriptionPopup, setShowNoSubscriptionPopup] = useState(false)
  const [subscriptionDetail, setSubscriptionDetail] = useState<SubscriptionDetail | null>(null)
  const [showSubManage, setShowSubManage] = useState(false)
  const [showCancelPopup, setShowCancelPopup] = useState(false)
  const [autoRenewToggling, setAutoRenewToggling] = useState(false)
  const [cancelContact, setCancelContact] = useState('')
  const [cancelMessage, setCancelMessage] = useState('')
  const [cancelSending, setCancelSending] = useState(false)
  const [showCancelSuccess, setShowCancelSuccess] = useState(false)
  const [showCancelContactError, setShowCancelContactError] = useState(false)
  const [profileTaglineTitle, setProfileTaglineTitle] = useState('')
  const [profileTagline, setProfileTagline] = useState('')
  const [expandedTagline, setExpandedTagline] = useState(false)
  const activeTabRef = useRef(activeTab)
  activeTabRef.current = activeTab

  useEffect(() => {
    if (!hasAuth) return
    if (activeTab === 'bookmarks') fetchBookmarks()
  }, [activeTab, hasAuth])

  useEffect(() => {
    if (!isSubscribed) return
    subscriptionApi.getDetail().then((res) => {
      if (res.data?.success && res.data.data) setSubscriptionDetail(res.data.data)
    }).catch(() => {})
  }, [isSubscribed])

  useEffect(() => {
    siteSettingsApi
      .getSite()
      .then((res) => {
        const d = res.data?.data
        if (typeof d?.profile_page_tagline_title === 'string') setProfileTaglineTitle(d.profile_page_tagline_title)
        if (typeof d?.profile_page_tagline === 'string') setProfileTagline(d.profile_page_tagline)
      })
      .catch(() => {})
  }, [])

  const fetchBookmarks = async () => {
    setIsLoading(true)
    try {
      const response = await newsApi.getBookmarks(1, 20)
      if (activeTabRef.current === 'bookmarks' && response.data.success) {
        setBookmarks(response.data.data.items || [])
      }
    } catch (error) {
      console.error('Failed to fetch bookmarks:', error)
    } finally {
      if (activeTabRef.current === 'bookmarks') setIsLoading(false)
    }
  }

  const [bookmarkDeletingId, setBookmarkDeletingId] = useState<number | null>(null)

  const handleRemoveBookmark = async (newsId: number) => {
    setBookmarkDeletingId(newsId)
    try {
      await newsApi.removeBookmark(newsId)
      setBookmarks((prev) => prev.filter((b) => b.id !== newsId))
    } catch (error) {
      alert(apiErrorMessage(error, '즐겨찾기 삭제에 실패했습니다.'))
    } finally {
      setBookmarkDeletingId(null)
    }
  }

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <div className="min-h-screen bg-page pb-24">
      <div className={CONTAINER_CLASS}>
        <header className="pt-12 pb-6">
          <div className="flex items-center justify-between gap-4">
            <div className="min-w-0 flex-1">
              {hasAuth ? (
                user ? (
                  <div className="p-4 md:p-5 bg-page-secondary rounded-xl border border-page flex flex-wrap items-stretch sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-3 md:gap-4 min-w-0 flex-1">
                      {user.profile_image ? (
                        <img
                          src={user.profile_image}
                          alt={user.nickname}
                          className="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover ring-1 ring-[var(--border-color)] shrink-0"
                        />
                      ) : (
                        <div className="w-12 h-12 md:w-14 md:h-14 rounded-full bg-page-secondary flex items-center justify-center ring-1 ring-[var(--border-color)] shrink-0 border border-page">
                          <span className="text-lg font-serif text-page-secondary">{user.nickname.charAt(0)}</span>
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="text-page font-medium truncate text-[1.4875rem] md:text-[1.7rem] leading-tight">{user.nickname}</p>
                        {user.role === 'admin' && (
                          <p className="text-page-secondary text-xs md:text-sm mt-0.5">관리자</p>
                        )}
                        {isSubscribed && (
                          <div className="mt-1.5">
                            {subscriptionDetail?.start_date && user?.subscription_expires_at ? (
                              <p className="text-[11px] font-bold text-orange-500 dark:text-orange-400">
                                {subscriptionDetail.plan_name || 'the gist 구독권'} ({formatDateKorean(subscriptionDetail.start_date)} ~ {formatDateKorean(user.subscription_expires_at)})
                              </p>
                            ) : user?.subscription_expires_at ? (
                              <p className="text-[11px] font-bold text-orange-500 dark:text-orange-400">
                                the gist 구독권 (만료: {formatDateKorean(user.subscription_expires_at)})
                              </p>
                            ) : (
                              <p className="text-[11px] font-bold text-orange-500 dark:text-orange-400">the gist 구독권</p>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                    <div className="flex flex-col justify-center gap-2.5 shrink-0 w-[7.5rem] sm:w-28">
                      {hasAuth && !isSubscribed && (
                        <Link
                          to="/subscribe"
                          className="inline-flex w-full items-center justify-center min-h-9 px-3 text-xs font-medium text-white bg-primary-500 hover:bg-primary-600 rounded-lg transition-colors text-center"
                        >
                          구독하기
                        </Link>
                      )}
                      <div className="flex min-h-9 items-center justify-end">
                        <button
                          type="button"
                          onClick={handleLogout}
                          className="text-page-secondary hover:text-page text-xs font-medium transition-colors"
                        >
                          로그아웃
                        </button>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="py-6 flex justify-center">
                    <LoadingSpinner size="large" />
                  </div>
                )
              ) : (
                <div className="p-4 bg-page rounded-xl border border-page">
                  <p className="text-page-secondary text-sm mb-3">로그인하면 즐겨찾기와 설정을 이용할 수 있어요.</p>
                  <Link
                    to="/login"
                    className="inline-block px-4 py-2.5 bg-primary-500 text-white text-sm font-medium rounded-lg hover:bg-primary-600 transition-colors"
                  >
                    로그인
                  </Link>
                </div>
              )}
            </div>
          </div>
        </header>

        {hasAuth && profileTaglineTitle.trim() !== '' && profileTagline.trim() !== '' && (
          <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page mb-4">
            <ul className="divide-y divide-[var(--border-color)]">
              <li>
                <button
                  type="button"
                  onClick={() => setExpandedTagline((v) => !v)}
                  className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${
                    expandedTagline ? 'bg-page-secondary' : 'hover:bg-page-secondary/50'
                  }`}
                >
                  <h2 className="flex-1 m-0 text-left text-xs font-bold text-primary-500 uppercase tracking-wider line-clamp-2">
                    {profileTaglineTitle}
                  </h2>
                  <MaterialIcon
                    name="chevron_right"
                    className={`w-5 h-5 text-page-muted transition-transform flex-shrink-0 ${expandedTagline ? 'rotate-90' : ''}`}
                    size={20}
                  />
                </button>
                {expandedTagline && (
                  <div className="px-5 pb-5 pt-1 min-h-0 border-t border-page">
                    <p className="text-page text-sm font-medium leading-relaxed whitespace-pre-line">{profileTagline}</p>
                  </div>
                )}
              </li>
            </ul>
          </section>
        )}

        {/* My Library: icon + label + chevron rows */}
        <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">My Library</h2>
          <ul className="divide-y divide-[var(--border-color)]">
            <li>
              <button
                type="button"
                onClick={() => setActiveTab((prev) => (prev === 'bookmarks' ? null : 'bookmarks'))}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${
                  activeTab === 'bookmarks' ? 'bg-page-secondary' : 'hover:bg-page-secondary/50'
                }`}
              >
                <MaterialIcon name="bookmark" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                <span className="flex-1 text-page text-sm font-medium">즐겨찾기</span>
                <MaterialIcon name="chevron_right" className={`w-5 h-5 text-page-muted transition-transform ${activeTab === 'bookmarks' ? 'rotate-90' : ''}`} size={20} />
              </button>
              {activeTab === 'bookmarks' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-page">
                  {!hasAuth ? (
                    <div className="text-center py-10">
                      <p className="text-page-secondary text-sm mb-4">로그인하면 볼 수 있어요.</p>
                      <Link to="/login" className="inline-block px-5 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600">로그인</Link>
                    </div>
                  ) : isLoading ? (
                    <div className="flex justify-center py-12"><LoadingSpinner size="large" /></div>
                  ) : (
                    <BookmarkList
                      bookmarks={bookmarks}
                      subCategoryToLabel={subCategoryToLabel}
                      onDelete={handleRemoveBookmark}
                      deletingId={bookmarkDeletingId}
                    />
                  )}
                </div>
              )}
            </li>
            <li>
              <button
                type="button"
                onClick={() => setActiveTab((prev) => (prev === 'audio' ? null : 'audio'))}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${
                  activeTab === 'audio' ? 'bg-page-secondary' : 'hover:bg-page-secondary/50'
                }`}
              >
                <MaterialIcon name="headphones" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                <span className="flex-1 text-page text-sm font-medium">들었던 오디오</span>
                <MaterialIcon name="chevron_right" className={`w-5 h-5 text-page-muted transition-transform ${activeTab === 'audio' ? 'rotate-90' : ''}`} size={20} />
              </button>
              {activeTab === 'audio' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-page">
                  {!hasAuth ? (
                    <div className="text-center py-10">
                      <p className="text-page-secondary text-sm mb-4">로그인하면 볼 수 있어요.</p>
                      <Link to="/login" className="inline-block px-5 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600">로그인</Link>
                    </div>
                  ) : (
                    <AudioList items={Array.isArray(audioItems) ? audioItems : []} subCategoryToLabel={subCategoryToLabel} />
                  )}
                </div>
              )}
            </li>
          </ul>
        </section>

        {/* My Subscription */}
        <section className="mt-6 bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">My Subscription</h2>
          <ul className="divide-y divide-[var(--border-color)]">
            <li>
              <button
                type="button"
                onClick={() => {
                  if (!isSubscribed) {
                    setShowNoSubscriptionPopup(true)
                  } else {
                    setShowSubManage((v) => !v)
                  }
                }}
                className={`w-full flex items-center justify-between px-5 py-4 text-left transition-colors ${showSubManage ? 'bg-page-secondary' : 'hover:bg-page-secondary/50'}`}
              >
                <span className="flex items-center gap-2 text-page text-sm font-medium">
                  <MaterialIcon name="credit_score" className="w-5 h-5 text-page-secondary" size={20} />
                  구독 관리
                </span>
                <MaterialIcon name="chevron_right" className={`w-5 h-5 text-page-muted transition-transform ${showSubManage ? 'rotate-90' : ''}`} size={20} />
              </button>
              <AnimatePresence>
                {showSubManage && isSubscribed && (
                  <motion.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: 'auto', opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    className="overflow-hidden"
                  >
                    <div className="px-5 pb-5 pt-3 border-t border-page space-y-5">
                      {/* 사용중인 구독 플랜 */}
                      <div className="bg-page-secondary rounded-lg p-4 border border-page">
                        <p className="text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">사용중인 플랜</p>
                        <p className="text-page font-medium text-sm">
                          {subscriptionDetail?.plan_name || 'the gist 구독권'} 사용중
                        </p>
                        {subscriptionDetail?.start_date && user?.subscription_expires_at && (
                          <p className="text-page-secondary text-xs mt-1">
                            {formatDateKorean(subscriptionDetail.start_date)} ~ {formatDateKorean(user.subscription_expires_at)}
                          </p>
                        )}
                      </div>

                      {/* 자동연장 토글 */}
                      <div>
                        <div className="flex items-center justify-between">
                          <span className="text-page text-sm font-medium">자동 연장</span>
                          <button
                            type="button"
                            role="switch"
                            aria-checked={subscriptionDetail?.auto_renew ?? true}
                            disabled={autoRenewToggling}
                            onClick={async () => {
                              if (autoRenewToggling) return
                              const newVal = !(subscriptionDetail?.auto_renew ?? true)
                              setAutoRenewToggling(true)
                              try {
                                const res = await subscriptionApi.setAutoRenew(newVal)
                                if (res.data?.success) {
                                  setSubscriptionDetail((d) => d ? { ...d, auto_renew: newVal, status: newVal ? 'ACTIVE' : 'PENDING_CANCEL', status_label: newVal ? '활성화' : '해지 예정' } : null)
                                }
                              } catch { /* ignore */ } finally {
                                setAutoRenewToggling(false)
                              }
                            }}
                            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-0 ring-0 border-0 disabled:opacity-50 ${
                              (subscriptionDetail?.auto_renew ?? true) ? 'bg-primary-500' : 'bg-page-secondary'
                            }`}
                          >
                            <span
                              className={`pointer-events-none inline-block h-5 w-5 shrink-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ${
                                (subscriptionDetail?.auto_renew ?? true) ? 'translate-x-5' : 'translate-x-0.5'
                              }`}
                            />
                          </button>
                        </div>
                        <p className="text-page-secondary text-xs mt-2">
                          구독 기간이 만료 되면 고객님의{' '}
                          <span className="font-bold text-page">
                            {(subscriptionDetail?.auto_renew ?? true)
                              ? '현재 플랜으로 자동 연장됩니다.'
                              : '구독이 종료 됩니다.'}
                          </span>
                        </p>
                      </div>

                      {/* 구독 취소 및 환불 */}
                      <button
                        type="button"
                        onClick={() => setShowCancelPopup(true)}
                        className="w-full py-2.5 rounded-lg border border-red-300 dark:border-red-500/30 text-red-500 text-sm font-medium hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                      >
                        구독 취소 및 환불
                      </button>
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </li>
          </ul>
        </section>

        {/* Settings: 다크 모드, 문의하기, 회원 탈퇴 */}
        <section className="mt-6 bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">Settings</h2>
          <ul className="divide-y divide-[var(--border-color)]">
            <li className="flex items-center justify-between gap-3 px-5 py-4">
              <span className="flex items-center gap-3 text-page text-sm font-medium">
                <MaterialIcon name="eyeglasses_2" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                다크 모드
              </span>
              <button
                type="button"
                role="switch"
                aria-checked={theme === 'dark'}
                onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
                className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-0 ring-0 border-0 ${
                  theme === 'dark' ? 'bg-primary-500' : 'bg-page-secondary'
                }`}
              >
                <span
                  className={`pointer-events-none inline-block h-5 w-5 shrink-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ${
                    theme === 'dark' ? 'translate-x-5' : 'translate-x-0.5'
                  }`}
                />
              </button>
            </li>
            <li>
              <button
                type="button"
                onClick={() => setExpandedActivity(expandedActivity === 'contact' ? 'none' : 'contact')}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${expandedActivity === 'contact' ? 'bg-page-secondary' : 'hover:bg-page-secondary/50'}`}
              >
                <MaterialIcon name="help" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                <span className="flex-1 text-page text-sm font-medium">문의하기</span>
                <MaterialIcon name="chevron_right" className={`w-5 h-5 text-page-muted transition-transform ${expandedActivity === 'contact' ? 'rotate-90' : ''}`} size={20} />
              </button>
              <AnimatePresence>
                {expandedActivity === 'contact' && (
                  <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                    <div className="px-5 pb-4 pt-1 border-t border-page">
                      <ContactForm />
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </li>
            {hasAuth && (
              <li>
                <button
                  type="button"
                  onClick={() => setShowWithdrawConfirm(true)}
                  className="w-full flex items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-page-secondary/50"
                >
                  <MaterialIcon name="person_cancel" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                  <span className="flex-1 text-page text-sm font-medium">회원 탈퇴</span>
                </button>
              </li>
            )}
          </ul>
        </section>

      </div>

      {showWithdrawConfirm && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="withdraw-title">
          <div className={`bg-page border border-page rounded-xl shadow-xl w-full p-6 ${isSubscribed ? 'max-w-md' : 'max-w-sm'}`}>
            <h3 id="withdraw-title" className="text-lg font-semibold text-page mb-2">회원탈퇴</h3>
            {isSubscribed ? (
              <>
                <p className="text-sm font-bold text-orange-500 dark:text-orange-400 mb-6 leading-relaxed text-center">
                  (구독 관리 → 구독 취소 및 환불 → 담당자 연락 후 취소 및 환불 조치 → 회원탈퇴)
                </p>
                <div className="flex justify-center">
                  <button
                    type="button"
                    onClick={() => setShowWithdrawConfirm(false)}
                    className="px-8 py-2.5 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors"
                  >
                    확인
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="text-sm text-page-secondary mb-6">탈퇴하면 계정과 데이터가 삭제되며 복구할 수 없습니다. 정말 탈퇴하시겠습니까?</p>
                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setShowWithdrawConfirm(false)}
                    disabled={withdrawing}
                    className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
                  >
                    취소
                  </button>
                  <button
                    type="button"
                    onClick={async () => {
                      setWithdrawing(true)
                      try {
                        const token = localStorage.getItem('access_token')
                        if (token) {
                          await fetch('/api/auth/withdraw', {
                            method: 'POST',
                            headers: {
                              'Content-Type': 'application/json',
                              'Authorization': `Bearer ${token}`,
                              'X-Authorization': `Bearer ${token}`,
                            },
                          })
                        }
                      } catch { /* ignore */ }
                      localStorage.removeItem('consent_required')
                      localStorage.removeItem('welcome_popup')
                      localStorage.removeItem('access_token')
                      localStorage.removeItem('refresh_token')
                      localStorage.removeItem('user')
                      localStorage.removeItem('auth-storage')
                      localStorage.removeItem('is_subscribed')
                      window.location.href = '/login'
                    }}
                    disabled={withdrawing}
                    className="flex-1 py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50"
                  >
                    {withdrawing ? '처리 중...' : '탈퇴하기'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
      {showNoSubscriptionPopup && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="no-subscription-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <p id="no-subscription-title" className="text-page font-medium text-center">현재 구독 중인 상품이 없습니다.</p>
            <div className="mt-4 flex flex-col sm:flex-row gap-3">
              <Link
                to="/subscribe"
                onClick={() => setShowNoSubscriptionPopup(false)}
                className="flex-1 text-center py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
              >
                구독하기
              </Link>
              <button
                type="button"
                onClick={() => setShowNoSubscriptionPopup(false)}
                className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors"
              >
                돌아가기
              </button>
            </div>
          </div>
        </div>
      )}

      {showCancelPopup && (
        <div className="fixed inset-0 z-[100] flex items-start justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-md w-full p-6 my-8">
            <h3 id="cancel-title" className="text-lg font-semibold text-page mb-1">정말 취소 하시겠습니까?</h3>
            <p className="text-sm text-page-secondary mb-5">환불 관련 소통할 연락처를 남겨주세요.</p>

            <div className="space-y-3 mb-5">
              <div>
                <label htmlFor="cancel-contact" className="block text-sm text-page-secondary mb-1">연락처 (이메일 또는 휴대폰) <span className="text-red-500">*필수</span></label>
                <input
                  id="cancel-contact"
                  type="text"
                  value={cancelContact}
                  onChange={(e) => setCancelContact(e.target.value)}
                  placeholder="답변 받을 이메일 또는 휴대폰 번호"
                  className="w-full px-4 py-2 border border-page rounded-lg text-page placeholder-[var(--text-muted)] focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-page text-sm"
                />
              </div>
              <div>
                <label htmlFor="cancel-message" className="block text-sm text-page-secondary mb-1">취소 사유 (선택)</label>
                <textarea
                  id="cancel-message"
                  value={cancelMessage}
                  onChange={(e) => setCancelMessage(e.target.value)}
                  placeholder="취소 사유를 입력해 주세요."
                  rows={3}
                  className="w-full px-4 py-2 border border-page rounded-lg text-page placeholder-[var(--text-muted)] focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none bg-page text-sm"
                />
              </div>
            </div>

            <div className="bg-page-secondary rounded-lg p-4 border border-page mb-5 max-h-52 overflow-y-auto">
              <p className="text-xs font-semibold text-page mb-2">제6조 (환불 및 취소 정책)</p>
              <ol className="list-decimal list-inside text-xs text-page-secondary space-y-1.5 leading-relaxed">
                <li>고객은 구독 결제일로부터 7일 이내에 서비스 이용 이력이 없는 경우 전액 환불을 요청할 수 있습니다.</li>
                <li>구독 기간 중 해지하는 경우, 이미 이용한 기간에 해당하는 금액을 일할(255원/일) 공제한 후 잔여 기간에 대해 환불할 수 있습니다.</li>
                <li>할인 구독권(3개월, 6개월, 12개월)의 경우 환불 시 정상 월 구독료 기준으로 이용 기간을 산정하여 일할(255원/일) 차감 후 환불합니다.</li>
                <li>환불은 결제 수단과 동일한 방식으로 처리함을 원칙으로 합니다.</li>
                <li>당사의 귀책사유로 서비스가 장기간 제공되지 않는 경우, 이용자는 전액 또는 일부 환불을 요구할 수 있습니다.</li>
              </ol>
            </div>

            <div className="flex gap-3">
              <button
                type="button"
                disabled={cancelSending}
                onClick={async () => {
                  if (!cancelContact.trim()) {
                    setShowCancelContactError(true)
                    return
                  }
                  setCancelSending(true)
                  try {
                    await contactApi.send({
                      subject: '구독 취소 및 환불 요청',
                      contact: cancelContact.trim(),
                      message: cancelMessage.trim() || '구독 취소 및 환불을 요청합니다.',
                    })
                    setShowCancelPopup(false)
                    setCancelContact('')
                    setCancelMessage('')
                    setShowCancelSuccess(true)
                  } catch {
                    alert('요청에 실패했습니다. 잠시 후 다시 시도해주세요.')
                  } finally {
                    setCancelSending(false)
                  }
                }}
                className="flex-1 py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50"
              >
                {cancelSending ? '처리 중...' : '구독 취소하기'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowCancelPopup(false)
                  setCancelContact('')
                  setCancelMessage('')
                }}
                disabled={cancelSending}
                className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                유지하기
              </button>
            </div>
          </div>
        </div>
      )}

      {showCancelSuccess && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <p className="text-page font-medium text-center">구독 취소 요청이 접수되었습니다.</p>
            <p className="text-page-secondary text-xs text-center mt-2">담당자가 곧 연락 드릴 예정입니다.</p>
            <div className="mt-4 flex justify-center">
              <button
                type="button"
                onClick={() => setShowCancelSuccess(false)}
                className="px-6 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
              >
                확인
              </button>
            </div>
          </div>
        </div>
      )}

      {showCancelContactError && (
        <div className="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <p className="text-page font-medium text-center">연락처를 기입해 주세요</p>
            <div className="mt-4 flex justify-center">
              <button
                type="button"
                onClick={() => setShowCancelContactError(false)}
                className="px-6 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
              >
                확인
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function ContactForm() {
  const [subject, setSubject] = useState('')
  const [contact, setContact] = useState('')
  const [message, setMessage] = useState('')
  const [sending, setSending] = useState(false)
  const [result, setResult] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
  const [showSuccessPopup, setShowSuccessPopup] = useState(false)
  const [showContactError, setShowContactError] = useState(false)

  const resetForm = () => {
    setSubject('')
    setContact('')
    setMessage('')
    setResult(null)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!contact.trim()) {
      setShowContactError(true)
      return
    }
    if (!subject.trim()) {
      setResult({ type: 'error', text: '제목을 입력해주세요.' })
      return
    }
    if (!message.trim()) {
      setResult({ type: 'error', text: '내용을 입력해주세요.' })
      return
    }
    setSending(true)
    setResult(null)
    try {
      await contactApi.send({
        subject: subject.trim(),
        contact: contact.trim() || undefined,
        message: message.trim(),
      })
      setShowSuccessPopup(true)
    } catch (err: unknown) {
      setResult({
        type: 'error',
        text: apiErrorMessage(err, '전송에 실패했습니다. 잠시 후 다시 시도해주세요.'),
      })
    } finally {
      setSending(false)
    }
  }

  return (
    <>
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label htmlFor="contact-subject" className="block text-sm text-page-secondary mb-1">제목 <span className="text-red-500">*필수</span></label>
        <input
          id="contact-subject"
          type="text"
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          placeholder="문의 제목"
          className="w-full px-4 py-2 border border-page rounded-lg text-page placeholder-[var(--text-muted)] focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-page"
        />
      </div>
      <div>
        <label htmlFor="contact-contact" className="block text-sm text-page-secondary mb-1">연락처 (이메일 또는 휴대폰) <span className="text-red-500">*필수</span></label>
        <input
          id="contact-contact"
          type="text"
          value={contact}
          onChange={(e) => setContact(e.target.value)}
          placeholder="답변 받을 이메일 또는 휴대폰 번호"
          className="w-full px-4 py-2 border border-page rounded-lg text-page placeholder-[var(--text-muted)] focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-page"
        />
      </div>
      <div>
        <label htmlFor="contact-message" className="block text-sm text-page-secondary mb-1">내용</label>
        <textarea
          id="contact-message"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="문의 내용을 입력하세요."
          rows={4}
          className="w-full px-4 py-2 border border-page rounded-lg text-page placeholder-[var(--text-muted)] focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none bg-page"
        />
      </div>
      <button
        type="submit"
        disabled={sending}
        className="w-full py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 disabled:opacity-50 transition-colors"
      >
        {sending ? '전송 중...' : '보내기'}
      </button>
      {result && result.type === 'error' && (
        <p className="text-sm text-red-500">{result.text}</p>
      )}
    </form>
    {showSuccessPopup && (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="contact-success-title">
        <div className="bg-page rounded-xl shadow-xl max-w-sm w-full p-6 border border-page">
          <p id="contact-success-title" className="text-page font-medium text-center">문의가 접수 되었습니다.</p>
          <div className="mt-4 flex justify-center">
            <button
              type="button"
              onClick={() => {
                setShowSuccessPopup(false)
                resetForm()
              }}
              className="px-6 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
            >
              확인
            </button>
          </div>
        </div>
      </div>
    )}
    {showContactError && (
      <div className="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
          <p className="text-page font-medium text-center">연락처를 기입해 주세요</p>
          <div className="mt-4 flex justify-center">
            <button
              type="button"
              onClick={() => setShowContactError(false)}
              className="px-6 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
            >
              확인
            </button>
          </div>
        </div>
      </div>
    )}
    </>
  )
}

function AudioList({ items, subCategoryToLabel }: { items: AudioListItem[]; subCategoryToLabel: Record<string, string> }) {
  const removeAudioItem = useAudioListStore((s) => s.removeItem)
  const safeItems = Array.isArray(items) ? items.filter((i) => i != null && Number.isFinite(i.id)) : []
  if (safeItems.length === 0) {
    return (
      <div className="text-center py-8">
        <p className="text-page-secondary text-sm">들었던 오디오가 없습니다.</p>
        <p className="text-page-muted text-xs mt-1">기사에서 음성 재생 버튼을 누르면 여기에 기록됩니다.</p>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-[var(--border-color)]">
      {safeItems.map((item, index) => (
        <motion.div
          key={item.listenedAt ? `${item.id}-${item.listenedAt}` : `audio-${item.id}-${index}`}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
          className="flex items-stretch gap-2 -mx-5 px-5 py-4 hover:bg-page-secondary/50 transition-colors rounded-lg group"
        >
          <Link to={`/news/${item.id}`} className="flex-1 min-w-0 block">
            <h3 className="font-medium text-page mb-1 line-clamp-2 hover:text-page-secondary transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs text-page-secondary">
              <span className="font-medium text-primary-500">
                {item.category ? (subCategoryToLabel[item.category] ?? item.category) : (formatSourceDisplayName(item.source) || 'the gist.')}
              </span>
              {(item.published_at || item.listenedAt) && (
                <>
                  <span className="text-page-muted">|</span>
                  <span>
                    {item.published_at
                      ? `${new Date(item.published_at).getFullYear()}년 ${new Date(item.published_at).getMonth() + 1}월 ${new Date(item.published_at).getDate()}일`
                      : item.listenedAt
                        ? new Date(item.listenedAt).toLocaleDateString('ko-KR')
                        : ''}
                  </span>
                </>
              )}
            </div>
          </Link>
          <button
            type="button"
            onClick={() => removeAudioItem(item.id)}
            className="shrink-0 self-center p-2 rounded-lg text-page-muted hover:text-red-500 hover:bg-page-secondary border border-transparent hover:border-[var(--border-color)] transition-colors"
            aria-label="들었던 목록에서 삭제"
            title="삭제"
          >
            <MaterialIcon name="delete" className="w-5 h-5" size={20} />
          </button>
        </motion.div>
      ))}
    </div>
  )
}

function BookmarkList({
  bookmarks,
  subCategoryToLabel,
  onDelete,
  deletingId,
}: {
  bookmarks: BookmarkRow[]
  subCategoryToLabel: Record<string, string>
  onDelete: (newsId: number) => void
  deletingId: number | null
}) {
  if (bookmarks.length === 0) {
    return (
      <div className="text-center py-8">
        <div className="text-page-muted mb-3">
          <MaterialIcon name="bookmark" className="w-12 h-12 mx-auto" size={48} />
        </div>
        <p className="text-page-secondary text-sm">즐겨찾기가 없습니다.</p>
        <Link
          to="/"
          className="inline-block mt-3 px-5 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600 transition-colors"
        >
          뉴스 둘러보기
        </Link>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-[var(--border-color)]">
      {bookmarks.map((item, index) => {
        const categoryLabel = item.category ? (subCategoryToLabel[item.category] ?? item.category) : (formatSourceDisplayName(item.source) || 'the gist.')
        const dateStr = item.published_at
          ? `${new Date(item.published_at).getFullYear()}년 ${new Date(item.published_at).getMonth() + 1}월 ${new Date(item.published_at).getDate()}일`
          : ''
        return (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
            className="flex items-stretch gap-2 -mx-5 px-5 py-4 hover:bg-page-secondary/50 transition-colors rounded-lg group"
          >
            <Link to={`/news/${item.id}`} className="flex-1 min-w-0 block">
              <h3 className="font-medium text-page mb-1 line-clamp-2 hover:text-page-secondary transition-colors text-sm">
                {item.title}
              </h3>
              <div className="flex items-center gap-2 text-xs text-page-secondary">
                <span className="font-medium text-primary-500">{categoryLabel}</span>
                {dateStr && (
                  <>
                    <span className="text-page-muted">|</span>
                    <span>{dateStr}</span>
                  </>
                )}
              </div>
            </Link>
            <button
              type="button"
              disabled={deletingId === item.id}
              onClick={() => onDelete(item.id)}
              className="shrink-0 self-center p-2 rounded-lg text-page-muted hover:text-red-500 hover:bg-page-secondary border border-transparent hover:border-[var(--border-color)] transition-colors disabled:opacity-40"
              aria-label="즐겨찾기에서 삭제"
              title="삭제"
            >
              <MaterialIcon name="delete" className="w-5 h-5" size={20} />
            </button>
          </motion.div>
        )
      })}
    </div>
  )
}

import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore, type AudioListItem } from '../store/audioListStore'
import { useViewSettingsStore, type Theme } from '../store/viewSettingsStore'
import { newsApi, siteSettingsApi, contactApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { formatSourceDisplayName } from '../utils/formatSource'
import MaterialIcon from '../components/Common/MaterialIcon'
import PrivacyPolicyModal from '../components/Common/PrivacyPolicyModal'
import TermsModal from '../components/Common/TermsModal'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

/** 기사 리스트와 동일: 하위 카테고리 라벨 (8개 + 직접입력은 그대로) */
const subCategoryToLabel: Record<string, string> = {
  politics_diplomacy: 'Politics/Diplomacy',
  economy_industry: 'Economy/Industry',
  society: 'Society',
  security_conflict: 'Security/Conflict',
  environment: 'Environment',
  science_technology: 'Science/Technology',
  culture: 'Culture',
  health_development: 'Health/Development',
}

export default function ProfilePage() {
  const { user, isAuthenticated, isSubscribed, logout } = useAuthStore()
  const { theme, setTheme } = useViewSettingsStore()
  const hasAuth = isAuthenticated
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<'bookmarks' | 'audio' | null>(null)
  const audioItems = useAudioListStore((s) => s.items)
  const [bookmarks, setBookmarks] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [siteSettings, setSiteSettings] = useState<{
    the_gist_vision: string
    copyright_text: string
  } | null>(null)
  const [showTerms, setShowTerms] = useState(false)
  const [showPrivacy, setShowPrivacy] = useState(false)
  const [expandedActivity, setExpandedActivity] = useState<'none' | 'contact'>('none')
  const [showWithdrawConfirm, setShowWithdrawConfirm] = useState(false)
  const [withdrawing, setWithdrawing] = useState(false)
  const [aiFeedExpanded, setAiFeedExpanded] = useState(false)
  const activeTabRef = useRef(activeTab)
  activeTabRef.current = activeTab

  useEffect(() => {
    if (!hasAuth) return
    if (activeTab === 'bookmarks') fetchBookmarks()
  }, [activeTab, hasAuth])

  useEffect(() => {
    siteSettingsApi.getSite().then((res) => {
      if (res.data?.data) {
        setSiteSettings({
          the_gist_vision: res.data.data.the_gist_vision ?? 'Gisters, Becoming Leaders',
          copyright_text: res.data.data.copyright_text || `© ${new Date().getFullYear()} The Gist`,
        })
      }
    }).catch(() => {})
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
                  <div className="p-4 md:p-5 bg-page-secondary rounded-xl border border-page flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3 md:gap-4 min-w-0">
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
                        <p className="text-page font-medium text-sm md:text-base truncate">{user.nickname}</p>
                        <p className="text-page-secondary text-xs md:text-sm mt-0.5">
                          {user.role === 'admin' ? '관리자' : 'Premium Member'}
                        </p>
                        {isSubscribed && (
                          <div className="mt-1.5 space-y-1">
                            <span className="inline-block px-2.5 py-0.5 text-[10px] font-medium tracking-wide uppercase text-primary-700 bg-primary-100 rounded-md">
                              SUBSCRIBER
                            </span>
                            {user?.subscription_expires_at && (
                              <p className="text-[10px] text-gray-400">
                                만료일: {new Date(user.subscription_expires_at).toLocaleDateString('ko-KR')}
                              </p>
                            )}
                          </div>
                        )}
                        {hasAuth && !isSubscribed && (
                          <Link
                            to="/subscribe"
                            className="inline-block mt-2 px-3 py-1.5 text-xs font-medium text-white bg-primary-500 hover:bg-primary-600 rounded-lg transition-colors"
                          >
                            구독하기
                          </Link>
                        )}
                      </div>
                    </div>
                    <button
                      onClick={handleLogout}
                      className="text-page-secondary hover:text-page text-xs font-medium transition-colors shrink-0"
                    >
                      로그아웃
                    </button>
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

        {/* My Library: icon + label + chevron rows */}
        <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-sm font-bold text-primary-500 uppercase tracking-wider">My Library</h2>
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
                    <BookmarkList bookmarks={bookmarks} />
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
                    <AudioList items={Array.isArray(audioItems) ? audioItems : []} />
                  )}
                </div>
              )}
            </li>
          </ul>
        </section>

        {/* My Subscription: Current Plan + MANAGE */}
        <section className="mt-6 bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-sm font-bold text-primary-500 uppercase tracking-wider">My Subscription</h2>
          <ul className="divide-y divide-[var(--border-color)]">
            <li className="flex items-center justify-between px-5 py-4">
              <span className="flex items-center gap-2 text-page text-sm font-medium">
                <MaterialIcon name="credit_score" className="w-5 h-5 text-page-secondary" size={20} />
                현재 플랜
              </span>
              <button
                type="button"
                className="px-3 py-1.5 text-xs font-medium tracking-wide uppercase text-white bg-primary-500 hover:bg-primary-600 rounded transition-colors"
              >
                MANAGE
              </button>
            </li>
          </ul>
        </section>

        {/* Settings: 다크 모드, 문의하기, 회원 탈퇴 */}
        <section className="mt-6 bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <h2 className="px-5 py-4 text-sm font-bold text-primary-500 uppercase tracking-wider">Settings</h2>
          <ul className="divide-y divide-[var(--border-color)]">
            <li className="flex items-center justify-between gap-3 px-5 py-4">
              <span className="flex items-center gap-3 text-page text-sm font-medium">
                <MaterialIcon name="eyeglasses_2" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                다크 모드
              </span>
              <div className="flex gap-2">
                {(['light', 'dark'] as Theme[]).map((t) => (
                  <button
                    key={t}
                    type="button"
                    onClick={() => setTheme(t)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                      theme === t ? 'bg-primary-500 text-white' : 'bg-page-secondary text-page-secondary hover:opacity-90'
                    }`}
                  >
                    {t === 'light' ? '라이트' : '다크'}
                  </button>
                ))}
              </div>
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
                  className="w-full flex items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-page-secondary/50 text-page-secondary"
                >
                  <MaterialIcon name="delete" className="w-5 h-5 text-page-muted flex-shrink-0" size={20} />
                  <span className="flex-1 text-sm font-medium">회원 탈퇴</span>
                </button>
              </li>
            )}
          </ul>
        </section>

        {/* AI Intelligence Feed: placeholder + Edit + expand/collapse */}
        <section className="mt-6 bg-page rounded-xl overflow-hidden shadow-sm border border-page">
          <div className="flex items-center justify-between px-5 py-4">
            <h2 className="text-sm font-medium text-page-secondary uppercase tracking-wider">AI Intelligence Feed</h2>
            <div className="flex items-center gap-2">
              <button type="button" className="text-xs font-medium text-page-secondary hover:text-page">Edit</button>
              <button
                type="button"
                onClick={() => setAiFeedExpanded((v) => !v)}
                className="p-1 text-page-muted hover:text-page-secondary"
                aria-label={aiFeedExpanded ? '접기' : '펼치기'}
              >
                <MaterialIcon name={aiFeedExpanded ? 'expand_less' : 'expand_more'} className="w-5 h-5" size={20} />
              </button>
            </div>
          </div>
          <AnimatePresence>
            {aiFeedExpanded && (
              <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                <div className="px-5 pb-5 pt-1 border-t border-page">
                  <div className="space-y-3">
                    {['80%', '60%', '100%', '40%'].map((w, i) => (
                      <div key={i} className="flex items-center gap-2">
                        <span className="w-1.5 h-1.5 rounded-full bg-primary-400 flex-shrink-0" />
                        <div className="h-3 bg-page-secondary rounded" style={{ width: w }} />
                      </div>
                    ))}
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </section>

        {/* Footer: The Gist, 저작권, 이용약관 — 정가운데 정렬 */}
        <footer className="mt-12 pt-8 pb-16 border-t border-page text-center">
          <div className="space-y-4 text-page-muted text-xs flex flex-col items-center justify-center">
            {siteSettings && (
              <>
                <p className="font-serif text-page-secondary text-sm leading-relaxed whitespace-pre-wrap">{siteSettings.the_gist_vision}</p>
                <p>{siteSettings.copyright_text}</p>
              </>
            )}
            <div className="flex gap-4 pt-2 justify-center">
              <button type="button" onClick={() => setShowTerms(true)} className="hover:text-page transition-colors underline underline-offset-2">
                이용약관
              </button>
              <button type="button" onClick={() => setShowPrivacy(true)} className="hover:text-page transition-colors underline underline-offset-2">
                개인정보 처리방침
              </button>
            </div>
          </div>
        </footer>
      </div>

      <TermsModal isOpen={showTerms} onClose={() => setShowTerms(false)} />
      <PrivacyPolicyModal isOpen={showPrivacy} onClose={() => setShowPrivacy(false)} />
      {showWithdrawConfirm && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="withdraw-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <h3 id="withdraw-title" className="text-lg font-semibold text-page mb-2">회원 탈퇴</h3>
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
          </div>
        </div>
      )}
    </div>
  )
}

function ContactForm() {
  const [subject, setSubject] = useState('')
  const [message, setMessage] = useState('')
  const [sending, setSending] = useState(false)
  const [result, setResult] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!message.trim()) {
      setResult({ type: 'error', text: '내용을 입력해주세요.' })
      return
    }
    setSending(true)
    setResult(null)
    try {
      await contactApi.send({ subject: subject.trim() || undefined, message: message.trim() })
      setResult({ type: 'success', text: '문의가 접수되었습니다.' })
      setSubject('')
      setMessage('')
    } catch (err: any) {
      setResult({
        type: 'error',
        text: err.response?.data?.message ?? '전송에 실패했습니다. 잠시 후 다시 시도해주세요.',
      })
    } finally {
      setSending(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label htmlFor="contact-subject" className="block text-sm text-page-secondary mb-1">제목 (선택)</label>
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
        <label htmlFor="contact-message" className="block text-sm text-page-secondary mb-1">내용 *</label>
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
      {result && (
        <p className={`text-sm ${result.type === 'success' ? 'text-page-secondary' : 'text-page-secondary'}`}>
          {result.text}
        </p>
      )}
    </form>
  )
}

function AudioList({ items }: { items: AudioListItem[] }) {
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
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-page-secondary/50 transition-colors -mx-5 px-5 rounded-lg">
            <h3 className="font-medium text-page mb-1 line-clamp-2 hover:text-page-secondary transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs text-page-secondary">
              <span className="font-medium text-primary-500">
                {item.category ? (subCategoryToLabel[item.category] ?? item.category) : (formatSourceDisplayName(item.source) || 'The Gist')}
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
        </motion.div>
      ))}
    </div>
  )
}

function BookmarkList({ bookmarks }: { bookmarks: any[] }) {
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
        const categoryLabel = item.category ? (subCategoryToLabel[item.category] ?? item.category) : (formatSourceDisplayName(item.source) || 'The Gist')
        const dateStr = item.published_at
          ? `${new Date(item.published_at).getFullYear()}년 ${new Date(item.published_at).getMonth() + 1}월 ${new Date(item.published_at).getDate()}일`
          : ''
        return (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
          >
            <Link to={`/news/${item.id}`} className="block py-4 hover:bg-page-secondary/50 transition-colors -mx-5 px-5 rounded-lg">
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
          </motion.div>
        )
      })}
    </div>
  )
}

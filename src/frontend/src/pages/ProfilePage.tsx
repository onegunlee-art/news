import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore, type AudioListItem } from '../store/audioListStore'
import { useViewSettingsStore, type FontSize, type Theme } from '../store/viewSettingsStore'
import { newsApi, siteSettingsApi, contactApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { formatSourceDisplayName } from '../utils/formatSource'
import PrivacyPolicyModal from '../components/Common/PrivacyPolicyModal'
import TermsModal from '../components/Common/TermsModal'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

export default function ProfilePage() {
  const { user, isAuthenticated, isSubscribed, logout, initializeAuth } = useAuthStore()
  const hasAuth = !!user || isAuthenticated || !!localStorage.getItem('access_token')
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<'bookmarks' | 'audio'>('bookmarks')
  const audioItems = useAudioListStore((s) => s.items)
  const [bookmarks, setBookmarks] = useState<any[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [siteSettings, setSiteSettings] = useState<{
    the_gist_vision: string
    copyright_text: string
  } | null>(null)
  const [showTerms, setShowTerms] = useState(false)
  const [showPrivacy, setShowPrivacy] = useState(false)
  const [expandedActivity, setExpandedActivity] = useState<'none' | 'view' | 'contact'>('none')
  const [aiFeedExpanded, setAiFeedExpanded] = useState(false)
  const activeTabRef = useRef(activeTab)
  activeTabRef.current = activeTab

  useEffect(() => {
    if (!user && localStorage.getItem('access_token')) initializeAuth()
  }, [user, initializeAuth])

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
    <div className="min-h-screen bg-neutral-50 pb-24">
      <div className={CONTAINER_CLASS}>
        <header className="pt-12 pb-6">
          <div className="flex items-center justify-between gap-4">
            <div className="min-w-0 flex-1">
              {hasAuth ? (
                user ? (
                  <div className="p-4 md:p-5 bg-[#f5f0e8] rounded-xl border border-neutral-200 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3 md:gap-4 min-w-0">
                      {user.profile_image ? (
                        <img
                          src={user.profile_image}
                          alt={user.nickname}
                          className="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover ring-1 ring-neutral-200 shrink-0"
                        />
                      ) : (
                        <div className="w-12 h-12 md:w-14 md:h-14 rounded-full bg-neutral-200 flex items-center justify-center ring-1 ring-neutral-200 shrink-0">
                          <span className="text-lg font-serif text-neutral-600">{user.nickname.charAt(0)}</span>
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="text-neutral-900 font-medium text-sm md:text-base truncate">{user.nickname}</p>
                        <p className="text-neutral-600 text-xs md:text-sm mt-0.5">
                          {user.role === 'admin' ? '관리자' : 'Premium Member'}
                        </p>
                        {isSubscribed && (
                          <span className="inline-block mt-1.5 px-2.5 py-0.5 text-[10px] font-medium tracking-wide uppercase text-primary-700 bg-primary-100 rounded-md">
                            SUBSCRIBER
                          </span>
                        )}
                      </div>
                    </div>
                    <button
                      onClick={handleLogout}
                      className="text-neutral-500 hover:text-neutral-900 text-xs font-medium transition-colors shrink-0"
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
                <div className="p-4 bg-white rounded-xl border border-neutral-200">
                  <p className="text-neutral-600 text-sm mb-3">로그인하면 즐겨찾기와 설정을 이용할 수 있어요.</p>
                  <Link
                    to="/login"
                    className="inline-block px-4 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
                  >
                    로그인
                  </Link>
                </div>
              )}
            </div>
          </div>
        </header>

        {/* My Library: icon + label + chevron rows */}
        <section className="bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <h2 className="px-5 py-4 text-sm font-medium text-neutral-700 uppercase tracking-wider">My Library</h2>
          <ul className="divide-y divide-neutral-100">
            <li>
              <button
                type="button"
                onClick={() => setActiveTab((prev) => (prev === 'bookmarks' ? null : 'bookmarks'))}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${
                  activeTab === 'bookmarks' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'
                }`}
              >
                <svg className="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
                <span className="flex-1 text-neutral-900 text-sm font-medium">즐겨찾기</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${activeTab === 'bookmarks' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              {activeTab === 'bookmarks' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-neutral-100">
                  {!hasAuth ? (
                    <div className="text-center py-10">
                      <p className="text-neutral-500 text-sm mb-4">로그인하면 볼 수 있어요.</p>
                      <Link to="/login" className="inline-block px-5 py-2 bg-neutral-900 text-white text-sm rounded-lg hover:bg-neutral-800">로그인</Link>
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
                  activeTab === 'audio' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'
                }`}
              >
                <svg className="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <span className="flex-1 text-neutral-900 text-sm font-medium">들었던 오디오</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${activeTab === 'audio' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              {activeTab === 'audio' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-neutral-100">
                  {!hasAuth ? (
                    <div className="text-center py-10">
                      <p className="text-neutral-500 text-sm mb-4">로그인하면 볼 수 있어요.</p>
                      <Link to="/login" className="inline-block px-5 py-2 bg-neutral-900 text-white text-sm rounded-lg hover:bg-neutral-800">로그인</Link>
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
        <section className="mt-6 bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <h2 className="px-5 py-4 text-sm font-medium text-neutral-700 uppercase tracking-wider">My Subscription</h2>
          <ul className="divide-y divide-neutral-100">
            <li className="flex items-center justify-between px-5 py-4">
              <span className="text-neutral-900 text-sm font-medium">현재 플랜</span>
              <button
                type="button"
                className="px-3 py-1.5 text-xs font-medium tracking-wide uppercase text-white bg-primary-500 hover:bg-primary-600 rounded transition-colors"
              >
                MANAGE
              </button>
            </li>
          </ul>
        </section>

        {/* Recent Activity: 보기 설정, 문의하기 */}
        <section className="mt-6 bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <h2 className="px-5 py-4 text-sm font-medium text-neutral-700 uppercase tracking-wider">Recent Activity</h2>
          <ul className="divide-y divide-neutral-100">
            <li>
              <button
                type="button"
                onClick={() => setExpandedActivity(expandedActivity === 'view' ? 'none' : 'view')}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${expandedActivity === 'view' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'}`}
              >
                <svg className="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span className="flex-1 text-neutral-900 text-sm font-medium">보기 설정</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${expandedActivity === 'view' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              <AnimatePresence>
                {expandedActivity === 'view' && (
                  <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                    <div className="px-5 pb-4 pt-1 border-t border-neutral-100">
                      <ViewSettingsBlock />
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </li>
            <li>
              <button
                type="button"
                onClick={() => setExpandedActivity(expandedActivity === 'contact' ? 'none' : 'contact')}
                className={`w-full flex items-center gap-3 px-5 py-4 text-left transition-colors ${expandedActivity === 'contact' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'}`}
              >
                <svg className="w-5 h-5 text-neutral-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                <span className="flex-1 text-neutral-900 text-sm font-medium">문의하기</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${expandedActivity === 'contact' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              <AnimatePresence>
                {expandedActivity === 'contact' && (
                  <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                    <div className="px-5 pb-4 pt-1 border-t border-neutral-100">
                      <ContactForm />
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </li>
          </ul>
        </section>

        {/* AI Intelligence Feed: placeholder + Edit + expand/collapse */}
        <section className="mt-6 bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <div className="flex items-center justify-between px-5 py-4">
            <h2 className="text-sm font-medium text-neutral-700 uppercase tracking-wider">AI Intelligence Feed</h2>
            <div className="flex items-center gap-2">
              <button type="button" className="text-xs font-medium text-neutral-600 hover:text-neutral-900">Edit</button>
              <button
                type="button"
                onClick={() => setAiFeedExpanded((v) => !v)}
                className="p-1 text-neutral-500 hover:text-neutral-700"
                aria-label={aiFeedExpanded ? '접기' : '펼치기'}
              >
                <svg className={`w-5 h-5 transition-transform ${aiFeedExpanded ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                </svg>
              </button>
            </div>
          </div>
          <AnimatePresence>
            {aiFeedExpanded && (
              <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                <div className="px-5 pb-5 pt-1 border-t border-neutral-100">
                  <div className="space-y-3">
                    {['80%', '60%', '100%', '40%'].map((w, i) => (
                      <div key={i} className="flex items-center gap-2">
                        <span className="w-1.5 h-1.5 rounded-full bg-primary-400 flex-shrink-0" />
                        <div className="h-3 bg-neutral-100 rounded" style={{ width: w }} />
                      </div>
                    ))}
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </section>

        {/* Footer: The Gist, 저작권, 이용약관 — 정가운데 정렬 */}
        <footer className="mt-12 pt-8 pb-16 border-t border-neutral-200 text-center">
          <div className="space-y-4 text-neutral-500 text-xs flex flex-col items-center justify-center">
            {siteSettings && (
              <>
                <p className="font-serif text-neutral-700 text-sm leading-relaxed whitespace-pre-wrap">{siteSettings.the_gist_vision}</p>
                <p>{siteSettings.copyright_text}</p>
              </>
            )}
            <div className="flex gap-4 pt-2 justify-center">
              <button type="button" onClick={() => setShowTerms(true)} className="hover:text-neutral-900 transition-colors underline underline-offset-2">
                이용약관
              </button>
              <button type="button" onClick={() => setShowPrivacy(true)} className="hover:text-neutral-900 transition-colors underline underline-offset-2">
                개인정보 처리방침
              </button>
            </div>
          </div>
        </footer>
      </div>

      <TermsModal isOpen={showTerms} onClose={() => setShowTerms(false)} />
      <PrivacyPolicyModal isOpen={showPrivacy} onClose={() => setShowPrivacy(false)} />
    </div>
  )
}

function ViewSettingsBlock() {
  const { fontSize, theme, setFontSize, setTheme } = useViewSettingsStore()
  const options: { value: FontSize; label: string }[] = [
    { value: 'small', label: '작게' },
    { value: 'normal', label: '보통' },
    { value: 'large', label: '크게' },
  ]
  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm text-neutral-600 mb-2">글씨 크기</p>
        <div className="flex gap-2">
          {options.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setFontSize(opt.value)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                fontSize === opt.value ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>
      <div className="flex items-center justify-between pt-4 border-t border-neutral-100">
        <span className="text-sm text-neutral-700">다크 모드</span>
        <div className="flex gap-2">
          {(['light', 'dark'] as Theme[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setTheme(t)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                theme === t ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
              }`}
            >
              {t === 'light' ? '라이트' : '다크'}
            </button>
          ))}
        </div>
      </div>
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
        <label htmlFor="contact-subject" className="block text-sm text-neutral-500 mb-1">제목 (선택)</label>
        <input
          id="contact-subject"
          type="text"
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          placeholder="문의 제목"
          className="w-full px-4 py-2 border border-neutral-200 rounded-lg text-neutral-900 placeholder-neutral-400 focus:ring-2 focus:ring-neutral-900 focus:border-transparent bg-white"
        />
      </div>
      <div>
        <label htmlFor="contact-message" className="block text-sm text-neutral-500 mb-1">내용 *</label>
        <textarea
          id="contact-message"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="문의 내용을 입력하세요."
          rows={4}
          className="w-full px-4 py-2 border border-neutral-200 rounded-lg text-neutral-900 placeholder-neutral-400 focus:ring-2 focus:ring-neutral-900 focus:border-transparent resize-none bg-white"
        />
      </div>
      <button
        type="submit"
        disabled={sending}
        className="w-full py-2.5 bg-neutral-900 text-white rounded-lg text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 transition-colors"
      >
        {sending ? '전송 중...' : '보내기'}
      </button>
      {result && (
        <p className={`text-sm ${result.type === 'success' ? 'text-neutral-600' : 'text-neutral-700'}`}>
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
        <p className="text-neutral-500 text-sm">들었던 오디오가 없습니다.</p>
        <p className="text-neutral-400 text-xs mt-1">기사에서 음성 재생 버튼을 누르면 여기에 기록됩니다.</p>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-neutral-100">
      {safeItems.map((item, index) => (
        <motion.div
          key={item.listenedAt ? `${item.id}-${item.listenedAt}` : `audio-${item.id}-${index}`}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-neutral-50 transition-colors -mx-5 px-5 rounded-lg">
            <h3 className="font-medium text-neutral-900 mb-1 line-clamp-2 hover:text-neutral-600 transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <span>{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              <span>·</span>
              <span>{item.listenedAt ? new Date(item.listenedAt).toLocaleDateString('ko-KR') : ''}</span>
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
        <div className="text-neutral-300 mb-3">
          <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </div>
        <p className="text-neutral-500 text-sm">즐겨찾기가 없습니다.</p>
        <Link
          to="/"
          className="inline-block mt-3 px-5 py-2 bg-neutral-900 text-white text-sm rounded-lg hover:bg-neutral-800 transition-colors"
        >
          뉴스 둘러보기
        </Link>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-neutral-100">
      {bookmarks.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-neutral-50 transition-colors -mx-5 px-5 rounded-lg">
            <h3 className="font-medium text-neutral-900 mb-1 line-clamp-2 hover:text-neutral-600 transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <span>{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              {item.bookmarked_at && (
                <>
                  <span>·</span>
                  <span>{new Date(item.bookmarked_at).toLocaleDateString('ko-KR')}</span>
                </>
              )}
            </div>
          </Link>
        </motion.div>
      ))}
    </div>
  )
}

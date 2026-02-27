import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
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
  const { user, isAuthenticated, logout, isSubscribed, setSubscribed } = useAuthStore()
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
  const activeTabRef = useRef(activeTab)
  activeTabRef.current = activeTab

  useEffect(() => {
    if (!isAuthenticated) return
    if (activeTab === 'bookmarks') fetchBookmarks()
  }, [activeTab, isAuthenticated])

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
        {/* Profile header block: avatar, membership badge only */}
        <header className="pt-12 pb-10">
          <h1 className="font-serif text-3xl md:text-4xl text-neutral-900 tracking-tight mb-8">
            My Page
          </h1>
          {user ? (
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                {user.profile_image ? (
                  <img
                    src={user.profile_image}
                    alt={user.nickname}
                    className="w-16 h-16 rounded-full object-cover ring-1 ring-neutral-200"
                  />
                ) : (
                  <div className="w-16 h-16 rounded-full bg-neutral-200 flex items-center justify-center ring-1 ring-neutral-200">
                    <span className="text-xl font-serif text-neutral-600">{user.nickname.charAt(0)}</span>
                  </div>
                )}
                <div>
                  <p className="text-neutral-900 font-medium text-sm">{user.nickname}</p>
                  {user.email && <p className="text-neutral-500 text-xs mt-0.5">{user.email}</p>}
                  <span className="inline-block mt-2 px-2.5 py-0.5 text-[10px] font-medium tracking-wide uppercase text-neutral-600 bg-neutral-100 rounded-full">
                    {user.role === 'admin' ? '관리자' : '회원'}
                  </span>
                </div>
              </div>
              <button
                onClick={handleLogout}
                className="text-neutral-500 hover:text-neutral-900 text-xs font-medium transition-colors"
              >
                로그아웃
              </button>
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-neutral-600 text-sm mb-4">로그인하면 즐겨찾기와 설정을 이용할 수 있어요.</p>
              <Link
                to="/login"
                className="inline-block px-5 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
              >
                로그인
              </Link>
            </div>
          )}
        </header>

        {/* Library section: two list items */}
        <section className="bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <ul className="divide-y divide-neutral-100">
            <li>
              <button
                type="button"
                onClick={() => setActiveTab('bookmarks')}
                className={`w-full flex items-center justify-between px-5 py-4 text-left transition-colors ${
                  activeTab === 'bookmarks' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'
                }`}
              >
                <span className="text-neutral-900 text-sm font-medium">저장한 콘텐츠</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${activeTab === 'bookmarks' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              {activeTab === 'bookmarks' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-neutral-100">
                  {!isAuthenticated ? (
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
                onClick={() => setActiveTab('audio')}
                className={`w-full flex items-center justify-between px-5 py-4 text-left transition-colors ${
                  activeTab === 'audio' ? 'bg-neutral-50' : 'hover:bg-neutral-50/50'
                }`}
              >
                <span className="text-neutral-900 text-sm font-medium">들었던 오디오</span>
                <svg className={`w-5 h-5 text-neutral-400 transition-transform ${activeTab === 'audio' ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
              {activeTab === 'audio' && (
                <div className="px-5 pb-5 pt-1 min-h-[180px] border-t border-neutral-100">
                  {!isAuthenticated ? (
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

        {/* Subscription section: single row */}
        <section className="mt-6 bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <div className="px-5 py-4">
            <h2 className="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-3">구독</h2>
            <SubscriptionBlock isSubscribed={isSubscribed} onCancel={() => setSubscribed(false)} />
          </div>
        </section>

        {/* Activity section: list items */}
        <section className="mt-6 bg-white rounded-xl overflow-hidden shadow-sm border border-neutral-100">
          <h2 className="px-5 py-4 text-xs font-medium text-neutral-500 uppercase tracking-wider">활동</h2>
          <ul className="divide-y divide-neutral-100">
            <li className="px-5 py-4">
              <p className="text-neutral-500 text-xs mb-3">새 글이 올라오면 푸시 알림</p>
              <NotificationToggle />
            </li>
            <li className="px-5 py-4">
              <ViewSettingsBlock />
            </li>
            <li className="px-5 py-4">
              <h3 className="text-neutral-900 text-sm font-medium mb-3">문의하기</h3>
              <ContactForm />
            </li>
          </ul>
        </section>

        {/* Footer: The Gist, 저작권, 이용약관 */}
        <footer className="mt-12 pt-8 pb-16 border-t border-neutral-200">
          <div className="space-y-4 text-neutral-500 text-xs">
            {siteSettings && (
              <>
                <p className="font-serif text-neutral-700 text-sm leading-relaxed whitespace-pre-wrap">{siteSettings.the_gist_vision}</p>
                <p>{siteSettings.copyright_text}</p>
              </>
            )}
            <div className="flex gap-4 pt-2">
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

function NotificationToggle() {
  const [on, setOn] = useState(false)
  return (
    <div className="flex items-center justify-between">
      <span className="text-sm text-neutral-700">새 글 푸시 알림</span>
      <button
        type="button"
        role="switch"
        aria-checked={on}
        onClick={() => setOn(!on)}
        className={`relative w-11 h-6 rounded-full transition-colors ${on ? 'bg-neutral-900' : 'bg-neutral-200'}`}
      >
        <span
          className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${on ? 'translate-x-5' : 'translate-x-0'}`}
        />
      </button>
    </div>
  )
}

function ViewSettingsBlock() {
  const { fontSize, grayscale, theme, setFontSize, toggleGrayscale, setTheme } = useViewSettingsStore()
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
      <div className="flex items-center justify-between pt-4 border-t border-neutral-100">
        <span className="text-sm text-neutral-700">화면 흑백</span>
        <button
          type="button"
          role="switch"
          aria-checked={grayscale}
          onClick={toggleGrayscale}
          className={`relative w-11 h-6 rounded-full transition-colors ${grayscale ? 'bg-neutral-900' : 'bg-neutral-200'}`}
        >
          <span
            className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${grayscale ? 'translate-x-5' : 'translate-x-0'}`}
          />
        </button>
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

function SubscriptionBlock({
  isSubscribed,
  onCancel,
}: {
  isSubscribed: boolean
  onCancel: () => void
}) {
  return (
    <div className="flex items-center justify-between py-1">
      <p className="text-sm text-neutral-700">
        {isSubscribed ? <strong>구독 중</strong> : '구독 안 함'}
      </p>
      {isSubscribed ? (
        <button type="button" onClick={onCancel} className="text-xs text-neutral-500 hover:text-neutral-900 underline underline-offset-2">
          해지하기
        </button>
      ) : (
        <Link to="/register" className="text-sm text-neutral-900 font-medium hover:underline underline-offset-2">
          구독하러 가기 →
        </Link>
      )}
    </div>
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
        <p className="text-neutral-500 text-sm">저장한 콘텐츠가 없습니다.</p>
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

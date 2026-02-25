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
    <div className="min-h-screen bg-gray-50 pb-12">
      {/* 페이지 헤더 */}
      <div className="bg-primary-500 pt-8 pb-16">
        <div className={CONTAINER_CLASS}>
          <h1
            className="text-3xl text-black text-center"
            style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
          >
            My Page
          </h1>
        </div>
      </div>

      <div className={`${CONTAINER_CLASS} -mt-10 space-y-6`}>
        {/* 프로필 카드 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <div className="flex flex-col sm:flex-row items-center gap-6">
            {user ? (
              <>
                <div className="relative">
                  {user.profile_image ? (
                    <img
                      src={user.profile_image}
                      alt={user.nickname}
                      className="w-20 h-20 rounded-full object-cover ring-4 ring-primary-500/30"
                    />
                  ) : (
                    <div className="w-20 h-20 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center ring-4 ring-primary-500/30">
                      <span className="text-2xl font-bold text-white">{user.nickname.charAt(0)}</span>
                    </div>
                  )}
                </div>
                <div className="flex-1 text-center sm:text-left">
                  <h2 className="text-xl font-bold text-gray-900 mb-1">{user.nickname}</h2>
                  {user.email && <p className="text-gray-500 text-sm mb-2">{user.email}</p>}
                  <div className="flex flex-wrap justify-center sm:justify-start gap-3 text-xs">
                    <span className="px-3 py-1 bg-primary-50 text-primary-600 rounded-full font-medium">
                      {user.role === 'admin' ? '관리자' : '회원'}
                    </span>
                    <span className="text-gray-400">
                      가입일: {new Date(user.created_at).toLocaleDateString('ko-KR')}
                    </span>
                  </div>
                </div>
                <button
                  onClick={handleLogout}
                  className="px-4 py-2 text-sm text-gray-500 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                >
                  로그아웃
                </button>
              </>
            ) : (
              <div className="flex-1 text-center py-2">
                <p className="text-gray-600 mb-3">로그인하면 즐겨찾기와 설정을 이용할 수 있어요.</p>
                <Link
                  to="/login"
                  className="inline-block px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors text-sm"
                >
                  로그인
                </Link>
              </div>
            )}
          </div>
        </motion.section>

        {/* 즐겨찾기 · 들었던 오디오 탭 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.05 }}
          className="bg-white rounded-2xl shadow-lg overflow-hidden"
        >
          <div className="flex border-b border-gray-100">
            <button
              onClick={() => setActiveTab('bookmarks')}
              className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
                activeTab === 'bookmarks' ? 'text-primary-500' : 'text-gray-500 hover:text-gray-900'
              }`}
            >
              즐겨찾기
              {activeTab === 'bookmarks' && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500" />
              )}
            </button>
            <button
              onClick={() => setActiveTab('audio')}
              className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
                activeTab === 'audio' ? 'text-primary-500' : 'text-gray-500 hover:text-gray-900'
              }`}
            >
              들었던 오디오
              {activeTab === 'audio' && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500" />
              )}
            </button>
          </div>
          <div className="p-4 min-h-[200px]">
            {!isAuthenticated ? (
              <div className="text-center py-10">
                <p className="text-gray-500 mb-4">로그인하면 볼 수 있어요.</p>
                <Link to="/login" className="inline-block px-6 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors">
                  로그인
                </Link>
              </div>
            ) : activeTab === 'audio' ? (
              <AudioList items={Array.isArray(audioItems) ? audioItems : []} />
            ) : isLoading ? (
              <div className="flex justify-center py-12">
                <LoadingSpinner size="large" />
              </div>
            ) : (
              <BookmarkList bookmarks={bookmarks} />
            )}
          </div>
        </motion.section>

        {/* 알림 설정 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <h3 className="text-base font-semibold text-gray-900 mb-3">알림 설정</h3>
          <p className="text-sm text-gray-500 mb-4">새 글이 올라오면 푸시 알림을 받을 수 있습니다.</p>
          <NotificationToggle />
        </motion.section>

        {/* 보기 설정 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.12 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <h3 className="text-base font-semibold text-gray-900 mb-3">보기 설정</h3>
          <ViewSettingsBlock />
        </motion.section>

        {/* 문의하기 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.14 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <h3 className="text-base font-semibold text-gray-900 mb-3">문의하기</h3>
          <ContactForm />
        </motion.section>

        {/* 구독 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.16 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <h3 className="text-base font-semibold text-gray-900 mb-3">구독</h3>
          <SubscriptionBlock isSubscribed={isSubscribed} onCancel={() => setSubscribed(false)} />
        </motion.section>

        {/* The Gist */}
        {siteSettings && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.18 }}
            className="bg-white rounded-2xl shadow-lg p-6"
          >
            <h3 className="text-base font-semibold text-gray-900 mb-2">The Gist</h3>
            <p className="text-sm text-gray-600 whitespace-pre-wrap">{siteSettings.the_gist_vision}</p>
          </motion.section>
        )}

        {/* 저작권 */}
        {siteSettings && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-2xl shadow-lg p-6"
          >
            <h3 className="text-base font-semibold text-gray-900 mb-2">저작권</h3>
            <p className="text-sm text-gray-500">{siteSettings.copyright_text}</p>
          </motion.section>
        )}

        {/* 이용약관 · 개인정보처리방침 */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.22 }}
          className="bg-white rounded-2xl shadow-lg p-6"
        >
          <div className="flex flex-wrap gap-4">
            <button
              type="button"
              onClick={() => setShowTerms(true)}
              className="text-sm text-gray-600 hover:text-primary-500 transition-colors underline underline-offset-2"
            >
              이용약관
            </button>
            <button
              type="button"
              onClick={() => setShowPrivacy(true)}
              className="text-sm text-gray-600 hover:text-primary-500 transition-colors underline underline-offset-2"
            >
              개인정보 처리 방침
            </button>
          </div>
        </motion.section>
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
      <span className="text-sm text-gray-700">새 글 푸시 알림</span>
      <button
        type="button"
        role="switch"
        aria-checked={on}
        onClick={() => setOn(!on)}
        className={`relative w-11 h-6 rounded-full transition-colors ${on ? 'bg-primary-500' : 'bg-gray-200'}`}
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
        <p className="text-sm text-page-secondary mb-2">글씨 크기</p>
        <div className="flex gap-2">
          {options.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setFontSize(opt.value)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                fontSize === opt.value
                  ? 'bg-primary-500 text-white'
                  : 'bg-page-secondary text-page-secondary hover:opacity-80'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>
      <div className="flex items-center justify-between pt-2 border-t border-page">
        <span className="text-sm text-page">다크 모드</span>
        <div className="flex gap-2">
          {(['light', 'dark'] as Theme[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setTheme(t)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                theme === t ? 'bg-primary-500 text-white' : 'bg-page-secondary text-page-secondary hover:opacity-80'
              }`}
            >
              {t === 'light' ? '라이트' : '다크'}
            </button>
          ))}
        </div>
      </div>
      <div className="flex items-center justify-between pt-2 border-t border-page">
        <span className="text-sm text-page">화면 흑백</span>
        <button
          type="button"
          role="switch"
          aria-checked={grayscale}
          onClick={toggleGrayscale}
          className={`relative w-11 h-6 rounded-full transition-colors ${grayscale ? 'bg-gray-700' : 'bg-page-secondary'}`}
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
        <label htmlFor="contact-subject" className="block text-sm text-gray-500 mb-1">제목 (선택)</label>
        <input
          id="contact-subject"
          type="text"
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          placeholder="문의 제목"
          className="w-full px-4 py-2 border border-gray-200 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        />
      </div>
      <div>
        <label htmlFor="contact-message" className="block text-sm text-gray-500 mb-1">내용 *</label>
        <textarea
          id="contact-message"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="문의 내용을 입력하세요."
          rows={4}
          className="w-full px-4 py-2 border border-gray-200 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
        />
      </div>
      <button
        type="submit"
        disabled={sending}
        className="w-full py-2.5 bg-primary-500 text-white rounded-lg font-medium hover:bg-primary-600 disabled:opacity-50 transition-colors"
      >
        {sending ? '전송 중...' : '보내기'}
      </button>
      {result && (
        <p className={`text-sm ${result.type === 'success' ? 'text-green-600' : 'text-red-600'}`}>
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
    <div className="space-y-3">
      <p className="text-sm text-gray-700">
        사용 중인 구독 플랜: <strong>{isSubscribed ? '구독 중' : '구독 안 함'}</strong>
      </p>
      {isSubscribed && (
        <button
          type="button"
          onClick={onCancel}
          className="text-sm text-red-500 hover:text-red-600 hover:underline"
        >
          해지하기
        </button>
      )}
      {!isSubscribed && (
        <Link
          to="/register"
          className="inline-block text-sm text-primary-500 hover:text-primary-600 font-medium"
        >
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
        <p className="text-gray-500 text-sm">들었던 오디오가 없습니다.</p>
        <p className="text-gray-400 text-xs mt-1">기사에서 음성 재생 버튼을 누르면 여기에 기록됩니다.</p>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-gray-100">
      {safeItems.map((item, index) => (
        <motion.div
          key={item.listenedAt ? `${item.id}-${item.listenedAt}` : `audio-${item.id}-${index}`}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-gray-50 transition-colors -mx-4 px-4">
            <h3 className="font-bold text-gray-900 mb-2 line-clamp-2 hover:text-primary-500 transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs">
              <span className="text-primary-500 font-medium">{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              <span className="text-gray-400">들은 날짜: {new Date(item.listenedAt).toLocaleDateString('ko-KR')}</span>
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
        <div className="text-gray-300 mb-3">
          <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
        </div>
        <p className="text-gray-500 text-sm">즐겨 찾기 등록한 컨텐츠가 없습니다.</p>
        <Link
          to="/"
          className="inline-block mt-3 px-5 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors text-sm"
        >
          뉴스 둘러보기
        </Link>
      </div>
    )
  }
  return (
    <div className="space-y-0 divide-y divide-gray-100">
      {bookmarks.map((item, index) => (
        <motion.div
          key={item.id}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.05 }}
        >
          <Link to={`/news/${item.id}`} className="block py-4 hover:bg-gray-50 transition-colors -mx-4 px-4">
            <h3 className="font-bold text-gray-900 mb-2 line-clamp-2 hover:text-primary-500 transition-colors text-sm">
              {item.title}
            </h3>
            <div className="flex items-center gap-2 text-xs">
              <span className="text-primary-500 font-medium">{formatSourceDisplayName(item.source) || 'The Gist'}</span>
              {item.bookmarked_at && (
                <span className="text-gray-400">저장일: {new Date(item.bookmarked_at).toLocaleDateString('ko-KR')}</span>
              )}
            </div>
          </Link>
        </motion.div>
      ))}
    </div>
  )
}

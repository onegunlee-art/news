import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { newsApi } from '../services/api'
import { shareToKakao } from '../services/kakaoAuth'
import { useAuthStore } from '../store/authStore'
import { useAudioListStore } from '../store/audioListStore'
import { useAudioPlayerStore } from '../store/audioPlayerStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getPlaceholderImageUrl } from '../utils/imagePolicy'

interface NewsItem {
  id?: number
  title: string
  description: string
  url: string
  source: string | null
  published_at: string | null
  time_ago?: string
  category?: string
  image_url?: string | null
}

type TabType = '최신' | '외교' | '금융' | '인기'

const tabToCategory: Record<TabType, string | null> = {
  '최신': null,
  '외교': 'diplomacy',
  '금융': 'economy',
  '인기': null,
}

export default function HomePage() {
  const [news, setNews] = useState<NewsItem[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<TabType>('최신')

  useEffect(() => {
    fetchNews()
  }, [activeTab])

  const fetchNews = async () => {
    setIsLoading(true)
    try {
      const category = tabToCategory[activeTab]
      const response = await newsApi.getList(1, 20, category || undefined)
      if (response.data.success) {
        const items = response.data.data.items || []
        // placeholder/잘못된 기사 제거 (예: "무엇이 처음부터 왔었" 등)
        const placeholderPhrases = ['무엇이 처음부터 왔었']
        const filtered = items.filter(
          (item: NewsItem) =>
            !placeholderPhrases.some(
              (phrase) =>
                (item.title && item.title.includes(phrase)) ||
                (item.description && item.description.includes(phrase))
            )
        )
        setNews(filtered)
      }
    } catch (error) {
      console.error('Failed to fetch news:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const tabs: TabType[] = ['최신', '외교', '금융', '인기']

  return (
    <div className="min-h-screen bg-white pb-20 md:pb-8">
      {/* 표지 (Cover) - 오렌지 배경 + 로고 + 서브타이틀 */}
      <section className="bg-primary-500 w-full min-h-[280px] md:min-h-[320px] lg:min-h-[360px] flex items-center justify-center py-12 md:py-16 lg:py-20">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4 text-center">
          <h2
            className="text-4xl md:text-5xl lg:text-6xl text-black"
            style={{ fontFamily: "'Lobster', cursive", fontWeight: 400 }}
          >
            The Gist
          </h2>
          <p className="text-black/90 text-base md:text-lg mt-3 md:mt-4 font-medium">
            가볍게 접하는 글로벌 저널
          </p>
        </div>
      </section>

      {/* 탭 네비게이션 */}
      <div className="sticky top-14 bg-white z-30 border-b border-gray-100">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4">
          <div className="flex">
            {tabs.map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`flex-1 py-3 text-sm font-medium transition-colors relative ${
                  activeTab === tab 
                    ? 'text-primary-500' 
                    : 'text-gray-500 hover:text-gray-900'
                }`}
              >
                {tab}
                {activeTab === tab && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* 기사 목록 - PC에서는 2열 그리드 */}
      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl mx-auto px-4 pt-4">
        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <LoadingSpinner size="large" />
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20 text-gray-500">
            기사가 없습니다.
          </div>
        ) : (
          <div className="space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-8 lg:gap-y-0 lg:border-t lg:border-gray-100">
            {news.map((item, index) => (
              <ArticleCard key={item.id || index} article={item} />
            ))}
          </div>
        )}
      </div>


      {/* 하단 네비게이션 - 모바일만 표시, PC는 헤더에 링크 */}
      <BottomNav />
    </div>
  )
}

// 기사 카드 - 왼쪽 텍스트 + 오른쪽 이미지 (기사별 고유 이미지, 중복 없음)
function ArticleCard({ article }: { article: NewsItem }) {
  const navigate = useNavigate()
  const { isAuthenticated } = useAuthStore()
  const addAudioItem = useAudioListStore((s) => s.addItem)
  const openAndPlay = useAudioPlayerStore((s) => s.openAndPlay)
  const [isBookmarked, setIsBookmarked] = useState(false)
  const [isBookmarking, setIsBookmarking] = useState(false)

  const imageUrl = article.image_url || getPlaceholderImageUrl(
    { id: article.id, title: article.title, description: article.description, published_at: article.published_at, category: article.category },
    200,
    200
  )

  // 날짜 포맷팅
  const formatDate = () => {
    if (article.time_ago) return article.time_ago
    if (article.published_at) {
      const date = new Date(article.published_at)
      return `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`
    }
    return ''
  }

  // 소스 이름 매핑
  const getSourceName = () => {
    if (article.source === 'Admin') return 'The Gist'
    return article.source || 'The Gist'
  }

  // 오디오 재생: 전역 팝업 플레이어에서 재생 (다른 페이지 이동해도 계속 재생)
  const handlePlayAudio = (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (!('speechSynthesis' in window)) {
      alert('이 브라우저는 음성 재생을 지원하지 않습니다.')
      return
    }
    const text = `${article.title}. ${article.description || ''}`.trim()
    if (!text) return
    const idForList = article.id ?? (article as any).news_id
    if (idForList) {
      addAudioItem({
        id: Number(idForList),
        title: article.title,
        description: article.description ?? null,
        source: article.source ?? null,
      })
    }
    openAndPlay(article.title, text, 1.0)
  }

  // 카카오톡 공유 핸들러
  const handleShare = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    
    const webUrl = `${window.location.origin}/news/${article.id}`
    await shareToKakao({
      title: article.title,
      description: article.description || '',
      imageUrl: imageUrl,
      webUrl: webUrl,
    })
  }

  // 즐겨찾기 핸들러
  const handleBookmark = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    
    const newsId = article.id ?? (article as any).news_id
    if (!newsId) {
      alert('이 기사는 즐겨찾기에 추가할 수 없습니다.')
      return
    }

    if (!isAuthenticated) {
      if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
        navigate('/login')
      }
      return
    }

    setIsBookmarking(true)
    try {
      if (isBookmarked) {
        await newsApi.removeBookmark(Number(newsId))
        setIsBookmarked(false)
      } else {
        await newsApi.bookmark(Number(newsId))
        setIsBookmarked(true)
      }
    } catch (err: any) {
      const msg = err.response?.data?.message ?? err.message ?? '즐겨찾기 처리에 실패했습니다.'
      alert(msg)
    } finally {
      setIsBookmarking(false)
    }
  }

  const newsId = article.id ?? (article as any).news_id
  const detailUrl = `/news/${newsId || ''}`

  return (
    <article className="flex gap-4 py-5 border-b border-gray-100 last:border-0 lg:border-b lg:border-gray-100">
      {/* 클릭 시 상세로 이동하는 영역만 Link */}
      <Link to={detailUrl} className="flex-1 min-w-0 flex gap-4">
        {/* 왼쪽 - 텍스트 콘텐츠 */}
        <div className="flex-1 min-w-0">
          {/* 제목 */}
          <h2 className="text-lg font-bold text-gray-900 leading-snug mb-2 line-clamp-2">
            {article.title}
          </h2>
          
          {/* 설명 */}
          {article.description && (
            <p className="text-sm text-gray-500 leading-relaxed mb-3 line-clamp-2">
              {article.description}
            </p>
          )}
          
          {/* 소스 및 날짜 */}
          <div className="flex items-center gap-2 text-xs">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-gray-300">/</span>
            <span className="text-gray-400">{formatDate()}</span>
          </div>
        </div>

        {/* 오른쪽 - 이미지 */}
        <div className="w-24 h-24 flex-shrink-0">
          <div className="w-full h-full bg-gray-100 rounded-lg overflow-hidden">
            <img
              src={imageUrl}
              alt={article.title}
              className="w-full h-full object-cover"
              onError={(e) => {
                (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                  { id: article.id, title: article.title, description: article.description, published_at: article.published_at, category: article.category, url: article.url, source: article.source },
                  200,
                  200
                )
              }}
            />
          </div>
        </div>
      </Link>

      {/* 액션 버튼들 - Link 밖에 두어 클릭 시 상세 이동 안 함 */}
      <div className="flex flex-col justify-between py-1" role="group" aria-label="기사 액션">
          {/* 오디오 재생 버튼 */}
          <button
            type="button"
            onClick={handlePlayAudio}
            className="p-1 transition-colors text-gray-300 hover:text-gray-500"
            title="음성으로 듣기"
            aria-label="재생"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M12 18.75a.75.75 0 01-.75-.75V6a.75.75 0 011.5 0v12a.75.75 0 01-.75.75zM8.25 15V9a.75.75 0 011.5 0v6a.75.75 0 01-1.5 0zM5.25 12.75v-1.5a.75.75 0 011.5 0v1.5a.75.75 0 01-1.5 0z" />
            </svg>
          </button>
          
          {/* 카카오톡 공유 버튼 */}
          <button
            type="button"
            onClick={handleShare}
            className="p-1 text-gray-300 hover:text-yellow-500 transition-colors"
            title="카카오톡으로 공유"
          >
            <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 3C6.5 3 2 6.58 2 11c0 2.83 1.82 5.32 4.56 6.74-.2.74-.73 2.68-.84 3.1-.13.53.19.52.41.38.17-.11 2.74-1.87 3.85-2.63.65.09 1.32.14 2.02.14 5.5 0 10-3.58 10-8s-4.5-8-10-8z"/>
            </svg>
          </button>
          
          {/* 즐겨찾기 버튼 */}
          <button
            type="button"
            onClick={handleBookmark}
            disabled={isBookmarking}
            className={`p-1 transition-colors ${isBookmarked ? 'text-primary-500' : 'text-gray-300 hover:text-gray-500'} ${isBookmarking ? 'opacity-60 cursor-wait' : ''}`}
            title="즐겨찾기"
            aria-label={isBookmarked ? '즐겨찾기 해제' : '즐겨찾기 추가'}
          >
            {isBookmarking ? (
              <span className="inline-block w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            ) : (
              <svg className="w-5 h-5" fill={isBookmarked ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
            )}
          </button>
        </div>
    </article>
  )
}

// 하단 네비게이션 - 모바일만 표시, PC는 헤더에 최신/즐겨찾기/설정 링크
function BottomNav() {
  return (
    <nav className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-40">
      <div className="max-w-lg mx-auto px-4">
        <div className="flex items-center justify-around h-16">
          {/* 최신 */}
          <Link to="/" className="flex flex-col items-center gap-1 text-gray-900">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span className="text-xs font-medium">최신</span>
          </Link>

          {/* My Page */}
          <Link to="/profile" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
            <span className="text-xs">My Page</span>
          </Link>

          {/* 설정 */}
          <Link to="/settings" className="flex flex-col items-center gap-1 text-gray-400 hover:text-gray-900 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span className="text-xs">설정</span>
          </Link>
        </div>
      </div>
    </nav>
  )
}

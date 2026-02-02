import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import {
  ChartBarIcon,
  UsersIcon,
  NewspaperIcon,
  CogIcon,
  ArrowTrendingUpIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  SparklesIcon,
  PlayIcon,
  DocumentTextIcon,
  SpeakerWaveIcon,
  AcademicCapIcon,
} from '@heroicons/react/24/outline';

interface DashboardStats {
  totalUsers: number;
  totalNews: number;
  totalAnalyses: number;
  todayUsers: number;
  todayAnalyses: number;
  apiStatus: {
    nyt: boolean;
    kakao: boolean;
    database: boolean;
  };
}

interface RecentActivity {
  id: number;
  type: 'user' | 'analysis' | 'news';
  message: string;
  time: string;
}

interface NewsArticle {
  id?: number;
  category: string;
  title: string;
  description?: string;
  content: string;
  source?: string;
  source_url?: string;
  created_at?: string;
}

const categories = [
  { id: 'diplomacy', name: 'Foreign Affairs', color: 'from-blue-500 to-cyan-500' },
  { id: 'economy', name: 'Economy', color: 'from-emerald-500 to-green-500' },
  { id: 'technology', name: 'Technology', color: 'from-purple-500 to-pink-500' },
  { id: 'entertainment', name: 'Entertainment', color: 'from-orange-500 to-red-500' },
];

// AI 분석 결과 인터페이스
interface AIAnalysisResult {
  translation_summary?: string;
  key_points?: string[];
  critical_analysis?: {
    why_important?: string;
    future_prediction?: string;
  };
  audio_url?: string;
}

const AdminPage: React.FC = () => {
  const navigate = useNavigate();
  const { } = useAuthStore(); // 권한 체크용 (추후 활성화)
  const [activeTab, setActiveTab] = useState<'dashboard' | 'users' | 'news' | 'ai' | 'settings'>('dashboard');
  
  // AI 분석 상태
  const [aiUrl, setAiUrl] = useState('');
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [aiResult, setAiResult] = useState<AIAnalysisResult | null>(null);
  const [aiError, setAiError] = useState<string | null>(null);
  // aiMockMode 제거됨 - The Gist AI 시스템으로 통합
  const [learningTexts, setLearningTexts] = useState('');
  const [isLearning, setIsLearning] = useState(false);
  const [learnedPatterns, setLearnedPatterns] = useState<any>(null);
  const [isSpeaking, setIsSpeaking] = useState(false);
  const [speechRate, setSpeechRate] = useState(1.0);

  // TTS 음성 읽기 함수
  const speakText = (text: string) => {
    if ('speechSynthesis' in window) {
      // 기존 음성 중지
      window.speechSynthesis.cancel();
      
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = 'ko-KR';
      utterance.rate = speechRate;
      utterance.pitch = 1.0;
      
      // 한국어 음성 찾기
      const voices = window.speechSynthesis.getVoices();
      const koreanVoice = voices.find(voice => voice.lang.includes('ko'));
      if (koreanVoice) {
        utterance.voice = koreanVoice;
      }
      
      utterance.onstart = () => setIsSpeaking(true);
      utterance.onend = () => setIsSpeaking(false);
      utterance.onerror = () => setIsSpeaking(false);
      
      window.speechSynthesis.speak(utterance);
    } else {
      alert('이 브라우저는 음성 합성을 지원하지 않습니다.');
    }
  };

  // 전체 분석 결과 읽기
  const speakFullAnalysis = () => {
    if (!aiResult) return;
    
    let fullText = '';
    
    if (aiResult.translation_summary) {
      fullText += '요약입니다. ' + aiResult.translation_summary + ' ';
    }
    
    if (aiResult.key_points && aiResult.key_points.length > 0) {
      fullText += '주요 포인트입니다. ';
      aiResult.key_points.forEach((point, i) => {
        fullText += `${i + 1}번. ${point}. `;
      });
    }
    
    if (aiResult.critical_analysis?.why_important) {
      fullText += '이게 왜 중요한가. ' + aiResult.critical_analysis.why_important + ' ';
    }
    
    if (aiResult.critical_analysis?.future_prediction) {
      fullText += '미래 전망입니다. ' + aiResult.critical_analysis.future_prediction;
    }
    
    speakText(fullText);
  };

  // 음성 중지
  const stopSpeaking = () => {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
      setIsSpeaking(false);
    }
  };
  
  // 뉴스 관리 상태
  const [selectedCategory, setSelectedCategory] = useState<string>('diplomacy');
  const [newsTitle, setNewsTitle] = useState('');
  const [newsContent, setNewsContent] = useState('');
  const [newsList, setNewsList] = useState<NewsArticle[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [editingNewsId, setEditingNewsId] = useState<number | null>(null);
  const [isLoadingNews, setIsLoadingNews] = useState(false);
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null);
  const [articleUrl, setArticleUrl] = useState('');
  const [isFetchingUrl, setIsFetchingUrl] = useState(false);
  
  const [stats, setStats] = useState<DashboardStats>({
    totalUsers: 0,
    totalNews: 0,
    totalAnalyses: 0,
    todayUsers: 0,
    todayAnalyses: 0,
    apiStatus: {
      nyt: false,
      kakao: false,
      database: false,
    },
  });
  const [recentActivities, setRecentActivities] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // 권한 체크 (실제 환경에서는 API 호출)
    // if (!isAuthenticated || user?.role !== 'admin') {
    //   navigate('/');
    //   return;
    // }

    loadDashboardData();
  }, []);

  // 뉴스 탭이 활성화되거나 카테고리가 변경될 때 뉴스 목록 로드
  useEffect(() => {
    if (activeTab === 'news') {
      loadNewsList();
    }
  }, [activeTab, selectedCategory]);

  // 기존 뉴스 목록 로드
  const loadNewsList = async () => {
    setIsLoadingNews(true);
    try {
      const response = await fetch(`/api/admin/news.php?category=${selectedCategory}`);
      const data = await response.json();
      if (data.success && data.data?.items) {
        setNewsList(data.data.items);
      }
    } catch (error) {
      console.error('Failed to load news:', error);
    } finally {
      setIsLoadingNews(false);
    }
  };

  // 뉴스 수정 시작
  const handleEditNews = (news: NewsArticle) => {
    setEditingNewsId(news.id || null);
    setNewsTitle(news.title);
    setNewsContent(news.content);
    // 스크롤을 폼 위치로 이동
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // 수정 취소
  const handleCancelEdit = () => {
    setEditingNewsId(null);
    setNewsTitle('');
    setNewsContent('');
    setArticleUrl('');
    setSaveMessage(null);
  };

  // 뉴스 삭제
  const handleDeleteNews = async (id: number) => {
    try {
      const response = await fetch(`/api/admin/news.php?id=${id}`, {
        method: 'DELETE',
      });
      const data = await response.json();
      if (data.success) {
        setSaveMessage({ type: 'success', text: '뉴스가 삭제되었습니다.' });
        setNewsList(prev => prev.filter(n => n.id !== id));
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      setSaveMessage({ type: 'error', text: '삭제 실패: ' + (error as Error).message });
    } finally {
      setDeleteConfirmId(null);
      setTimeout(() => setSaveMessage(null), 3000);
    }
  };

  const loadDashboardData = async () => {
    setLoading(true);
    
    // 실제 API 호출 대신 데모 데이터 사용
    setTimeout(() => {
      setStats({
        totalUsers: 127,
        totalNews: 1543,
        totalAnalyses: 892,
        todayUsers: 23,
        todayAnalyses: 45,
        apiStatus: {
          nyt: true,
          kakao: true,
          database: true,
        },
      });

      setRecentActivities([
        { id: 1, type: 'user', message: '새 사용자가 가입했습니다', time: '5분 전' },
        { id: 2, type: 'analysis', message: '뉴스 분석이 완료되었습니다', time: '12분 전' },
        { id: 3, type: 'news', message: 'NYT에서 새 뉴스를 가져왔습니다', time: '1시간 전' },
        { id: 4, type: 'user', message: '사용자가 로그인했습니다', time: '2시간 전' },
        { id: 5, type: 'analysis', message: '키워드 분석이 실행되었습니다', time: '3시간 전' },
      ]);

      setLoading(false);
    }, 500);
  };

  const StatCard: React.FC<{
    title: string;
    value: number | string;
    icon: React.ReactNode;
    change?: string;
    color: string;
  }> = ({ title, value, icon, change, color }) => (
    <div className={`bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-slate-400 text-sm">{title}</p>
          <p className="text-3xl font-bold text-white mt-2">{value}</p>
          {change && (
            <p className="text-emerald-400 text-sm mt-1 flex items-center gap-1">
              <ArrowTrendingUpIcon className="w-4 h-4" />
              {change}
            </p>
          )}
        </div>
        <div className={`p-4 rounded-xl ${color}`}>
          {icon}
        </div>
      </div>
    </div>
  );

  const ApiStatusBadge: React.FC<{ name: string; status: boolean }> = ({ name, status }) => (
    <div className="flex items-center justify-between py-3 px-4 bg-slate-900/50 rounded-lg">
      <span className="text-slate-300">{name}</span>
      <div className={`flex items-center gap-2 ${status ? 'text-emerald-400' : 'text-red-400'}`}>
        {status ? (
          <>
            <CheckCircleIcon className="w-5 h-5" />
            <span className="text-sm">정상</span>
          </>
        ) : (
          <>
            <ExclamationTriangleIcon className="w-5 h-5" />
            <span className="text-sm">오류</span>
          </>
        )}
      </div>
    </div>
  );

  const tabs = [
    { id: 'dashboard', name: '대시보드', icon: ChartBarIcon },
    { id: 'users', name: '사용자 관리', icon: UsersIcon },
    { id: 'news', name: '뉴스 관리', icon: NewspaperIcon },
    { id: 'ai', name: 'AI 분석', icon: SparklesIcon },
    { id: 'settings', name: '설정', icon: CogIcon },
  ] as const;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
      <div className="flex">
        {/* Sidebar */}
        <div className="w-64 min-h-screen bg-slate-900/80 backdrop-blur-xl border-r border-slate-700/50 p-6">
          <div className="mb-8">
            <h1 className="text-2xl font-bold bg-gradient-to-r from-cyan-400 to-emerald-400 bg-clip-text text-transparent">
              Admin Panel
            </h1>
            <p className="text-slate-500 text-sm mt-1">The Gist</p>
          </div>

          <nav className="space-y-2">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${
                  activeTab === tab.id
                    ? 'bg-gradient-to-r from-cyan-500/20 to-emerald-500/20 text-cyan-400 border border-cyan-500/30'
                    : 'text-slate-400 hover:bg-slate-800/50 hover:text-white'
                }`}
              >
                <tab.icon className="w-5 h-5" />
                {tab.name}
              </button>
            ))}
          </nav>

          <div className="mt-auto pt-8 border-t border-slate-700/50 mt-8">
            <button
              onClick={() => navigate('/')}
              className="w-full text-slate-400 hover:text-white text-sm py-2"
            >
              ← 홈으로 돌아가기
            </button>
          </div>
        </div>

        {/* Main Content */}
        <div className="flex-1 p-8">
          {activeTab === 'dashboard' && (
            <div className="space-y-8">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">대시보드</h2>
                <p className="text-slate-400">시스템 현황을 한눈에 확인하세요</p>
              </div>

              {loading ? (
                <div className="flex items-center justify-center py-20">
                  <div className="animate-spin rounded-full h-12 w-12 border-4 border-cyan-500 border-t-transparent"></div>
                </div>
              ) : (
                <>
                  {/* Stats Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                      title="전체 사용자"
                      value={stats.totalUsers}
                      icon={<UsersIcon className="w-6 h-6 text-white" />}
                      change="+12% 이번 주"
                      color="bg-gradient-to-br from-blue-500 to-blue-600"
                    />
                    <StatCard
                      title="저장된 뉴스"
                      value={stats.totalNews.toLocaleString()}
                      icon={<NewspaperIcon className="w-6 h-6 text-white" />}
                      change="+8% 이번 주"
                      color="bg-gradient-to-br from-emerald-500 to-emerald-600"
                    />
                    <StatCard
                      title="분석 완료"
                      value={stats.totalAnalyses}
                      icon={<ChartBarIcon className="w-6 h-6 text-white" />}
                      change="+23% 이번 주"
                      color="bg-gradient-to-br from-purple-500 to-purple-600"
                    />
                    <StatCard
                      title="오늘 분석"
                      value={stats.todayAnalyses}
                      icon={<ClockIcon className="w-6 h-6 text-white" />}
                      color="bg-gradient-to-br from-orange-500 to-orange-600"
                    />
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* API Status */}
                    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                      <h3 className="text-lg font-semibold text-white mb-4">API 상태</h3>
                      <div className="space-y-3">
                        <ApiStatusBadge name="NYT News API" status={stats.apiStatus.nyt} />
                        <ApiStatusBadge name="Kakao Login API" status={stats.apiStatus.kakao} />
                        <ApiStatusBadge name="MySQL Database" status={stats.apiStatus.database} />
                      </div>
                    </div>

                    {/* Recent Activity */}
                    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                      <h3 className="text-lg font-semibold text-white mb-4">최근 활동</h3>
                      <div className="space-y-3">
                        {recentActivities.map((activity) => (
                          <div
                            key={activity.id}
                            className="flex items-center gap-3 py-2 border-b border-slate-700/30 last:border-0"
                          >
                            <div
                              className={`w-2 h-2 rounded-full ${
                                activity.type === 'user'
                                  ? 'bg-blue-400'
                                  : activity.type === 'analysis'
                                  ? 'bg-purple-400'
                                  : 'bg-emerald-400'
                              }`}
                            />
                            <span className="text-slate-300 flex-1">{activity.message}</span>
                            <span className="text-slate-500 text-sm">{activity.time}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>

                  {/* Quick Actions */}
                  <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                    <h3 className="text-lg font-semibold text-white mb-4">빠른 작업</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <NewspaperIcon className="w-8 h-8 text-cyan-400 mb-2" />
                        <p className="text-white font-medium">뉴스 새로고침</p>
                        <p className="text-slate-500 text-sm">NYT API 호출</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <ChartBarIcon className="w-8 h-8 text-purple-400 mb-2" />
                        <p className="text-white font-medium">분석 리포트</p>
                        <p className="text-slate-500 text-sm">통계 다운로드</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <UsersIcon className="w-8 h-8 text-blue-400 mb-2" />
                        <p className="text-white font-medium">사용자 초대</p>
                        <p className="text-slate-500 text-sm">이메일 발송</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <CogIcon className="w-8 h-8 text-orange-400 mb-2" />
                        <p className="text-white font-medium">캐시 초기화</p>
                        <p className="text-slate-500 text-sm">시스템 정리</p>
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          )}

          {activeTab === 'users' && (
            <div className="space-y-6">
              <h2 className="text-2xl font-bold text-white">사용자 관리</h2>
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <p className="text-slate-400">사용자 관리 기능이 곧 추가됩니다.</p>
              </div>
            </div>
          )}

          {activeTab === 'news' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">뉴스 관리</h2>
                <p className="text-slate-400">카테고리별 뉴스를 작성하고 관리하세요</p>
              </div>

              {/* 카테고리 선택 네비게이션 */}
              <div className="flex gap-3 flex-wrap">
                {categories.map((cat) => (
                  <button
                    key={cat.id}
                    onClick={() => setSelectedCategory(cat.id)}
                    className={`px-5 py-3 rounded-xl font-medium transition-all ${
                      selectedCategory === cat.id
                        ? `bg-gradient-to-r ${cat.color} text-white shadow-lg`
                        : 'bg-slate-800/50 text-slate-300 hover:bg-slate-700/50 border border-slate-700/50'
                    }`}
                  >
                    {cat.name}
                  </button>
                ))}
              </div>

              {/* 뉴스 작성/수정 폼 */}
              <div className={`bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border ${editingNewsId ? 'border-amber-500/50' : 'border-slate-700/50'}`}>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-white">
                    {editingNewsId 
                      ? `뉴스 수정 중 (ID: ${editingNewsId})`
                      : `${categories.find(c => c.id === selectedCategory)?.name} 뉴스 작성`
                    }
                  </h3>
                  {editingNewsId && (
                    <button
                      onClick={handleCancelEdit}
                      className="flex items-center gap-1 px-3 py-1.5 text-sm text-amber-400 hover:text-amber-300 border border-amber-500/30 rounded-lg hover:bg-amber-500/10 transition"
                    >
                      <XMarkIcon className="w-4 h-4" />
                      수정 취소
                    </button>
                  )}
                </div>

                <div className="space-y-4">
                  {/* URL 자동 추출 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">기사 URL (선택사항)</label>
                    <div className="flex gap-2">
                      <input
                        type="url"
                        value={articleUrl}
                        onChange={(e) => setArticleUrl(e.target.value)}
                        placeholder="https://example.com/article..."
                        className="flex-1 bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                      />
                      <button
                        onClick={async () => {
                          if (!articleUrl.trim()) {
                            setSaveMessage({ type: 'error', text: 'URL을 입력해주세요.' });
                            return;
                          }
                          
                          setIsFetchingUrl(true);
                          setSaveMessage(null);
                          
                          // 메타데이터 추출 API 서비스들 (순차적으로 시도)
                          const metadataApis = [
                            // Microlink API (무료, 안정적)
                            async (url: string) => {
                              const response = await fetch(`https://api.microlink.io?url=${encodeURIComponent(url)}`);
                              const data = await response.json();
                              if (data.status === 'success' && data.data) {
                                return {
                                  title: data.data.title || '',
                                  description: data.data.description || '',
                                };
                              }
                              throw new Error('Microlink failed');
                            },
                            // JSONLink API
                            async (url: string) => {
                              const response = await fetch(`https://jsonlink.io/api/extract?url=${encodeURIComponent(url)}`);
                              const data = await response.json();
                              if (data.title || data.description) {
                                return {
                                  title: data.title || '',
                                  description: data.description || '',
                                };
                              }
                              throw new Error('JSONLink failed');
                            },
                            // LinkPreview API (백업)
                            async (url: string) => {
                              const response = await fetch(`https://api.linkpreview.net/?q=${encodeURIComponent(url)}`, {
                                headers: { 'X-Linkpreview-Api-Key': 'free' }
                              });
                              const data = await response.json();
                              if (data.title || data.description) {
                                return {
                                  title: data.title || '',
                                  description: data.description || '',
                                };
                              }
                              throw new Error('LinkPreview failed');
                            },
                          ];
                          
                          try {
                            let result = null;
                            
                            // 각 API를 순차적으로 시도
                            for (let i = 0; i < metadataApis.length; i++) {
                              try {
                                console.log(`Trying metadata API ${i + 1}...`);
                                
                                const controller = new AbortController();
                                const timeoutId = setTimeout(() => controller.abort(), 10000);
                                
                                result = await Promise.race([
                                  metadataApis[i](articleUrl),
                                  new Promise<never>((_, reject) => 
                                    setTimeout(() => reject(new Error('Timeout')), 10000)
                                  )
                                ]);
                                
                                clearTimeout(timeoutId);
                                
                                if (result && (result.title || result.description)) {
                                  console.log(`API ${i + 1} succeeded:`, result);
                                  break;
                                }
                              } catch (apiError) {
                                console.log(`API ${i + 1} failed:`, apiError);
                                continue;
                              }
                            }
                            
                            if (result && (result.title || result.description)) {
                              // HTML 엔티티 디코딩
                              const decodeHtml = (text: string) => {
                                const textarea = document.createElement('textarea');
                                textarea.innerHTML = text;
                                return textarea.value;
                              };
                              
                              setNewsTitle(decodeHtml(result.title));
                              setNewsContent(decodeHtml(result.description));
                              setSaveMessage({ type: 'success', text: '기사 정보를 가져왔습니다!' });
                            } else {
                              throw new Error('기사 정보를 가져올 수 없습니다. URL을 확인하거나 직접 입력해주세요.');
                            }
                          } catch (error) {
                            console.error('Metadata fetch error:', error);
                            setSaveMessage({ type: 'error', text: '오류: ' + (error as Error).message });
                          } finally {
                            setIsFetchingUrl(false);
                            setTimeout(() => setSaveMessage(null), 5000);
                          }
                        }}
                        disabled={isFetchingUrl || !articleUrl.trim()}
                        className={`px-5 py-3 rounded-xl font-medium transition-all whitespace-nowrap ${
                          isFetchingUrl || !articleUrl.trim()
                            ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                            : 'bg-gradient-to-r from-cyan-500 to-blue-500 text-white hover:opacity-90'
                        }`}
                      >
                        {isFetchingUrl ? '가져오는 중...' : '자동 추출'}
                      </button>
                    </div>
                    <p className="text-slate-500 text-sm mt-1">기사 URL을 입력하면 제목과 내용을 자동으로 가져옵니다.</p>
                  </div>

                  {/* 제목 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">뉴스 제목</label>
                    <input
                      type="text"
                      value={newsTitle}
                      onChange={(e) => setNewsTitle(e.target.value)}
                      placeholder="뉴스 제목을 입력하세요"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                    />
                  </div>

                  {/* 내용 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">뉴스 내용</label>
                    <textarea
                      value={newsContent}
                      onChange={(e) => setNewsContent(e.target.value)}
                      placeholder="뉴스 본문을 작성하세요..."
                      rows={8}
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition resize-none"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsContent.length} / 10,000자</p>
                  </div>

                  {/* 저장 버튼 */}
                  <div className="flex items-center gap-4">
                    <button
                      onClick={async () => {
                        if (!newsTitle.trim() || !newsContent.trim()) {
                          setSaveMessage({ type: 'error', text: '제목과 내용을 모두 입력해주세요.' });
                          return;
                        }
                        
                        setIsSaving(true);
                        setSaveMessage(null);
                        
                        try {
                          const isEditing = editingNewsId !== null;
                          const response = await fetch('/api/admin/news.php', {
                            method: isEditing ? 'PUT' : 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              ...(isEditing && { id: editingNewsId }),
                              category: selectedCategory,
                              title: newsTitle,
                              content: newsContent,
                              source_url: articleUrl.trim() || null,
                            }),
                          });
                          
                          const data = await response.json();
                          
                          if (data.success) {
                            setSaveMessage({ 
                              type: 'success', 
                              text: isEditing ? '뉴스가 수정되었습니다!' : '뉴스가 저장되었습니다!' 
                            });
                            // 목록 새로고침
                            await loadNewsList();
                            // 폼 초기화
                            setNewsTitle('');
                            setNewsContent('');
                            setArticleUrl('');
                            setEditingNewsId(null);
                          } else {
                            throw new Error(data.message || '저장 실패');
                          }
                        } catch (error) {
                          setSaveMessage({ type: 'error', text: '저장 실패: ' + (error as Error).message });
                        } finally {
                          setIsSaving(false);
                          setTimeout(() => setSaveMessage(null), 3000);
                        }
                      }}
                      disabled={isSaving || !newsTitle.trim() || !newsContent.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isSaving || !newsTitle.trim() || !newsContent.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : editingNewsId
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:opacity-90'
                            : 'bg-gradient-to-r from-cyan-500 to-emerald-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isSaving ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          저장 중...
                        </>
                      ) : editingNewsId ? (
                        <>
                          <PencilSquareIcon className="w-5 h-5" />
                          뉴스 수정
                        </>
                      ) : (
                        <>
                          <NewspaperIcon className="w-5 h-5" />
                          뉴스 저장
                        </>
                      )}
                    </button>

                    <button
                      onClick={handleCancelEdit}
                      className="px-6 py-3 rounded-xl font-medium bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition"
                    >
                      초기화
                    </button>
                  </div>

                  {/* 저장 메시지 */}
                  {saveMessage && (
                    <div className={`p-4 rounded-xl flex items-center gap-2 ${
                      saveMessage.type === 'success' 
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' 
                        : 'bg-red-500/20 text-red-400 border border-red-500/30'
                    }`}>
                      {saveMessage.type === 'success' ? (
                        <CheckCircleIcon className="w-5 h-5" />
                      ) : (
                        <ExclamationTriangleIcon className="w-5 h-5" />
                      )}
                      {saveMessage.text}
                    </div>
                  )}
                </div>
              </div>

              {/* 저장된 뉴스 목록 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-white">
                    {categories.find(c => c.id === selectedCategory)?.name} 뉴스 목록
                  </h3>
                  <span className="text-slate-400 text-sm">
                    총 {newsList.length}개
                  </span>
                </div>

                {isLoadingNews ? (
                  <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-4 border-cyan-500 border-t-transparent"></div>
                  </div>
                ) : newsList.length === 0 ? (
                  <p className="text-slate-500 text-center py-8">
                    이 카테고리에 저장된 뉴스가 없습니다.
                  </p>
                ) : (
                  <div className="space-y-3 max-h-[500px] overflow-y-auto">
                    {newsList.map((news) => (
                      <div
                        key={news.id}
                        className={`p-4 bg-slate-900/50 rounded-xl border transition-all ${
                          editingNewsId === news.id 
                            ? 'border-amber-500/50 bg-amber-500/5' 
                            : 'border-slate-700/30 hover:border-slate-600/50'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                              <span className="text-xs px-2 py-0.5 bg-slate-700 text-slate-300 rounded">
                                ID: {news.id}
                              </span>
                              {news.source && news.source !== 'Admin' && (
                                <span className="text-xs px-2 py-0.5 bg-blue-500/20 text-blue-400 rounded">
                                  {news.source}
                                </span>
                              )}
                            </div>
                            <h4 className="text-white font-medium truncate">{news.title}</h4>
                            <p className="text-slate-400 text-sm mt-1 line-clamp-2">
                              {news.description || news.content}
                            </p>
                            <div className="flex items-center gap-3 mt-2">
                              <p className="text-slate-500 text-xs">
                                {news.created_at ? new Date(news.created_at).toLocaleString('ko-KR') : ''}
                              </p>
                              {news.source_url && !news.source_url.startsWith('admin://') && (
                                <a
                                  href={news.source_url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="text-xs text-cyan-400 hover:text-cyan-300 hover:underline"
                                >
                                  원문 보기 →
                                </a>
                              )}
                            </div>
                          </div>
                          <div className="flex items-center gap-2 shrink-0">
                            <button
                              onClick={() => handleEditNews(news)}
                              disabled={editingNewsId === news.id}
                              className={`p-2 rounded-lg transition ${
                                editingNewsId === news.id
                                  ? 'bg-amber-500/20 text-amber-400 cursor-not-allowed'
                                  : 'text-slate-400 hover:text-cyan-400 hover:bg-cyan-500/10'
                              }`}
                              title="수정"
                            >
                              <PencilSquareIcon className="w-5 h-5" />
                            </button>
                            <button
                              onClick={() => setDeleteConfirmId(news.id || null)}
                              className="p-2 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition"
                              title="삭제"
                            >
                              <TrashIcon className="w-5 h-5" />
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* 삭제 확인 다이얼로그 */}
              {deleteConfirmId && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
                  <div className="bg-slate-800 rounded-2xl p-6 border border-slate-700 max-w-md w-full mx-4 shadow-2xl">
                    <div className="flex items-center gap-3 mb-4">
                      <div className="p-3 bg-red-500/20 rounded-full">
                        <TrashIcon className="w-6 h-6 text-red-400" />
                      </div>
                      <div>
                        <h3 className="text-lg font-semibold text-white">뉴스 삭제</h3>
                        <p className="text-slate-400 text-sm">이 작업은 되돌릴 수 없습니다.</p>
                      </div>
                    </div>
                    <p className="text-slate-300 mb-6">
                      ID {deleteConfirmId} 뉴스를 정말 삭제하시겠습니까?
                    </p>
                    <div className="flex gap-3 justify-end">
                      <button
                        onClick={() => setDeleteConfirmId(null)}
                        className="px-4 py-2 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition"
                      >
                        취소
                      </button>
                      <button
                        onClick={() => handleDeleteNews(deleteConfirmId)}
                        className="px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 transition"
                      >
                        삭제
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === 'ai' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">AI 뉴스 분석</h2>
                <p className="text-slate-400">URL을 입력하면 AI가 기사를 분석, 요약, 번역합니다</p>
              </div>

              {/* 상태 표시 */}
              <div className="flex items-center gap-4">
                <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                  <SparklesIcon className="w-5 h-5" />
                  The Gist AI 분석 시스템
                </div>
                <button
                  onClick={async () => {
                    try {
                      const response = await fetch('/api/admin/ai-analyze.php');
                      const data = await response.json();
                      setAiMockMode(data.mock_mode);
                    } catch (error) {
                      console.error('Status check failed:', error);
                    }
                  }}
                  className="text-slate-400 hover:text-white text-sm underline"
                >
                  상태 새로고침
                </button>
              </div>

              {/* URL 분석 섹션 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                  <DocumentTextIcon className="w-5 h-5 text-cyan-400" />
                  기사 URL 분석
                </h3>
                
                <div className="space-y-4">
                  <div className="flex gap-3">
                    <input
                      type="url"
                      value={aiUrl}
                      onChange={(e) => setAiUrl(e.target.value)}
                      placeholder="https://www.reuters.com/article/..."
                      className="flex-1 bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                    />
                    <button
                      onClick={async () => {
                        if (!aiUrl.trim()) {
                          setAiError('URL을 입력해주세요.');
                          return;
                        }
                        
                        setIsAnalyzing(true);
                        setAiError(null);
                        setAiResult(null);
                        
                        try {
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              action: 'analyze',
                              url: aiUrl,
                              enable_tts: false
                            })
                          });
                          
                          const data = await response.json();
                          
                          if (data.success && data.analysis) {
                            setAiResult(data.analysis);
                            setAiMockMode(data.mock_mode);
                          } else {
                            setAiError(data.error || '분석 실패');
                          }
                        } catch (error) {
                          setAiError('서버 오류: ' + (error as Error).message);
                        } finally {
                          setIsAnalyzing(false);
                        }
                      }}
                      disabled={isAnalyzing || !aiUrl.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isAnalyzing || !aiUrl.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-cyan-500 to-emerald-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isAnalyzing ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          분석 중...
                        </>
                      ) : (
                        <>
                          <PlayIcon className="w-5 h-5" />
                          AI 분석 실행
                        </>
                      )}
                    </button>
                  </div>

                  {/* 에러 메시지 */}
                  {aiError && (
                    <div className="p-4 rounded-xl bg-red-500/20 text-red-400 border border-red-500/30 flex items-center gap-2">
                      <ExclamationTriangleIcon className="w-5 h-5" />
                      {aiError}
                    </div>
                  )}

                  {/* 분석 결과 */}
                  {aiResult && (
                    <div className="space-y-4 pt-4 border-t border-slate-700/50">
                      {/* 요약 */}
                      {aiResult.translation_summary && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-cyan-400 font-medium mb-2 flex items-center gap-2">
                            <DocumentTextIcon className="w-4 h-4" />
                            번역 및 요약
                          </h4>
                          <p className="text-slate-300 leading-relaxed">{aiResult.translation_summary}</p>
                        </div>
                      )}

                      {/* 주요 포인트 */}
                      {aiResult.key_points && aiResult.key_points.length > 0 && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-emerald-400 font-medium mb-2">📌 주요 포인트</h4>
                          <ul className="space-y-2">
                            {aiResult.key_points.map((point, i) => (
                              <li key={i} className="text-slate-300 flex items-start gap-2">
                                <span className="text-emerald-400 mt-1">•</span>
                                {point}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}

                      {/* 크리티컬 분석 */}
                      {aiResult.critical_analysis && (
                        <div className="p-4 bg-gradient-to-br from-purple-500/10 to-pink-500/10 rounded-xl border border-purple-500/20">
                          <h4 className="text-purple-400 font-medium mb-3">🔥 이게 왜 중요한대!</h4>
                          
                          {aiResult.critical_analysis.why_important && (
                            <div className="mb-3">
                              <p className="text-slate-400 text-sm mb-1">중요성</p>
                              <p className="text-slate-200">{aiResult.critical_analysis.why_important}</p>
                            </div>
                          )}
                          
                          {aiResult.critical_analysis.future_prediction && (
                            <div>
                              <p className="text-slate-400 text-sm mb-1">미래 전망</p>
                              <p className="text-slate-200">{aiResult.critical_analysis.future_prediction}</p>
                            </div>
                          )}
                        </div>
                      )}

                      {/* 오디오 (있는 경우) */}
                      {aiResult.audio_url && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-orange-400 font-medium mb-2 flex items-center gap-2">
                            <SpeakerWaveIcon className="w-4 h-4" />
                            오디오 분석
                          </h4>
                          <audio controls className="w-full">
                            <source src={aiResult.audio_url} type="audio/mpeg" />
                          </audio>
                        </div>
                      )}

                      {/* 음성 읽기 컨트롤 */}
                      <div className="p-4 bg-slate-900/50 rounded-xl">
                        <div className="flex items-center justify-between mb-3">
                          <h4 className="text-orange-400 font-medium flex items-center gap-2">
                            <SpeakerWaveIcon className="w-4 h-4" />
                            AI 음성 읽기
                          </h4>
                          <div className="flex items-center gap-2">
                            <span className="text-slate-400 text-sm">속도:</span>
                            <select
                              value={speechRate}
                              onChange={(e) => setSpeechRate(parseFloat(e.target.value))}
                              className="bg-slate-800 text-white text-sm rounded px-2 py-1 border border-slate-700"
                            >
                              <option value="0.7">느리게</option>
                              <option value="1.0">보통</option>
                              <option value="1.3">빠르게</option>
                              <option value="1.5">매우 빠르게</option>
                            </select>
                          </div>
                        </div>
                        
                        <div className="flex gap-2">
                          <button
                            onClick={isSpeaking ? stopSpeaking : speakFullAnalysis}
                            className={`flex-1 py-3 rounded-xl font-medium transition flex items-center justify-center gap-2 ${
                              isSpeaking
                                ? 'bg-red-500 text-white hover:bg-red-600'
                                : 'bg-gradient-to-r from-orange-500 to-red-500 text-white hover:opacity-90'
                            }`}
                          >
                            {isSpeaking ? (
                              <>
                                <XMarkIcon className="w-5 h-5" />
                                읽기 중지
                              </>
                            ) : (
                              <>
                                <SpeakerWaveIcon className="w-5 h-5" />
                                전체 분석 읽어주기
                              </>
                            )}
                          </button>
                        </div>
                        
                        {/* 개별 섹션 읽기 */}
                        <div className="flex gap-2 mt-2">
                          <button
                            onClick={() => speakText(aiResult.translation_summary || '')}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            요약만
                          </button>
                          <button
                            onClick={() => speakText(aiResult.key_points?.join('. ') || '')}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            포인트만
                          </button>
                          <button
                            onClick={() => speakText(
                              (aiResult.critical_analysis?.why_important || '') + ' ' +
                              (aiResult.critical_analysis?.future_prediction || '')
                            )}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            분석만
                          </button>
                        </div>
                      </div>

                      {/* 뉴스로 저장 버튼 */}
                      <button
                        onClick={() => {
                          setActiveTab('news');
                          setNewsTitle(aiResult.translation_summary?.substring(0, 100) || '');
                          setNewsContent(
                            (aiResult.translation_summary || '') + '\n\n' +
                            '## 주요 포인트\n' + 
                            (aiResult.key_points?.map(p => `- ${p}`).join('\n') || '') + '\n\n' +
                            '## 분석\n' +
                            (aiResult.critical_analysis?.why_important || '') + '\n\n' +
                            '## 전망\n' +
                            (aiResult.critical_analysis?.future_prediction || '')
                          );
                          setArticleUrl(aiUrl);
                        }}
                        className="w-full py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white font-medium hover:opacity-90 transition flex items-center justify-center gap-2"
                      >
                        <NewspaperIcon className="w-5 h-5" />
                        이 분석을 뉴스로 저장
                      </button>
                    </div>
                  )}
                </div>
              </div>

              {/* 학습 섹션 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                  <AcademicCapIcon className="w-5 h-5 text-purple-400" />
                  스타일 학습
                </h3>
                <p className="text-slate-400 text-sm mb-4">
                  당신이 작성한 글을 입력하면 AI가 스타일을 학습하여 분석에 적용합니다.
                </p>

                <div className="space-y-4">
                  <textarea
                    value={learningTexts}
                    onChange={(e) => setLearningTexts(e.target.value)}
                    placeholder="학습시킬 글을 입력하세요... (여러 글은 --- 로 구분)"
                    rows={6}
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition resize-none"
                  />
                  
                  <div className="flex items-center gap-4">
                    <button
                      onClick={async () => {
                        if (!learningTexts.trim()) return;
                        
                        setIsLearning(true);
                        try {
                          const texts = learningTexts.split('---').map(t => t.trim()).filter(t => t);
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              action: 'learn',
                              texts
                            })
                          });
                          
                          const data = await response.json();
                          if (data.success) {
                            setLearnedPatterns(data.patterns);
                            setLearningTexts('');
                          }
                        } catch (error) {
                          console.error('Learning failed:', error);
                        } finally {
                          setIsLearning(false);
                        }
                      }}
                      disabled={isLearning || !learningTexts.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isLearning || !learningTexts.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-purple-500 to-pink-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isLearning ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          학습 중...
                        </>
                      ) : (
                        <>
                          <AcademicCapIcon className="w-5 h-5" />
                          스타일 학습
                        </>
                      )}
                    </button>

                    <button
                      onClick={async () => {
                        try {
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'status' })
                          });
                          const data = await response.json();
                          setLearnedPatterns(data.patterns);
                        } catch (error) {
                          console.error('Status check failed:', error);
                        }
                      }}
                      className="text-slate-400 hover:text-white text-sm underline"
                    >
                      학습 현황 확인
                    </button>
                  </div>

                  {/* 학습된 패턴 표시 */}
                  {learnedPatterns && Object.keys(learnedPatterns).length > 0 && (
                    <div className="p-4 bg-purple-500/10 rounded-xl border border-purple-500/20">
                      <h4 className="text-purple-400 font-medium mb-2">학습된 스타일</h4>
                      <pre className="text-slate-300 text-sm overflow-x-auto">
                        {JSON.stringify(learnedPatterns, null, 2)}
                      </pre>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {activeTab === 'settings' && (
            <div className="space-y-6">
              <h2 className="text-2xl font-bold text-white">설정</h2>
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
                <div>
                  <label className="block text-slate-300 mb-2">NYT API Key</label>
                  <input
                    type="text"
                    placeholder="YOUR_NYT_API_KEY"
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-4 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 mb-2">Kakao API Key</label>
                  <input
                    type="text"
                    placeholder="YOUR_KAKAO_API_KEY"
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-4 py-2 text-white"
                  />
                </div>
                <button className="bg-gradient-to-r from-cyan-500 to-emerald-500 text-white px-6 py-2 rounded-lg hover:opacity-90 transition">
                  설정 저장
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default AdminPage;

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
  content: string;
  created_at?: string;
}

const categories = [
  { id: 'diplomacy', name: '외교', color: 'from-blue-500 to-cyan-500' },
  { id: 'economy', name: '경제', color: 'from-emerald-500 to-green-500' },
  { id: 'technology', name: '기술', color: 'from-purple-500 to-pink-500' },
  { id: 'entertainment', name: 'Entertainment', color: 'from-orange-500 to-red-500' },
];

const AdminPage: React.FC = () => {
  const navigate = useNavigate();
  const { } = useAuthStore(); // 권한 체크용 (추후 활성화)
  const [activeTab, setActiveTab] = useState<'dashboard' | 'users' | 'news' | 'settings'>('dashboard');
  
  // 뉴스 관리 상태
  const [selectedCategory, setSelectedCategory] = useState<string>('diplomacy');
  const [newsTitle, setNewsTitle] = useState('');
  const [newsContent, setNewsContent] = useState('');
  const [newsList, setNewsList] = useState<NewsArticle[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  
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
            <p className="text-slate-500 text-sm mt-1">News Context Analysis</p>
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

              {/* 뉴스 작성 폼 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4">
                  {categories.find(c => c.id === selectedCategory)?.name} 뉴스 작성
                </h3>

                <div className="space-y-4">
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
                          // API 호출 (실제 환경)
                          const response = await fetch('/api/admin/news.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              category: selectedCategory,
                              title: newsTitle,
                              content: newsContent,
                            }),
                          });
                          
                          if (response.ok) {
                            setSaveMessage({ type: 'success', text: '뉴스가 성공적으로 저장되었습니다!' });
                            // 목록에 추가
                            setNewsList(prev => [{
                              id: Date.now(),
                              category: selectedCategory,
                              title: newsTitle,
                              content: newsContent,
                              created_at: new Date().toISOString(),
                            }, ...prev]);
                            // 폼 초기화
                            setNewsTitle('');
                            setNewsContent('');
                          } else {
                            throw new Error('저장 실패');
                          }
                        } catch (error) {
                          // 데모 모드: 로컬 저장
                          setSaveMessage({ type: 'success', text: '뉴스가 저장되었습니다! (데모 모드)' });
                          setNewsList(prev => [{
                            id: Date.now(),
                            category: selectedCategory,
                            title: newsTitle,
                            content: newsContent,
                            created_at: new Date().toISOString(),
                          }, ...prev]);
                          setNewsTitle('');
                          setNewsContent('');
                        } finally {
                          setIsSaving(false);
                          setTimeout(() => setSaveMessage(null), 3000);
                        }
                      }}
                      disabled={isSaving || !newsTitle.trim() || !newsContent.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isSaving || !newsTitle.trim() || !newsContent.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-cyan-500 to-emerald-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isSaving ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          저장 중...
                        </>
                      ) : (
                        <>
                          <NewspaperIcon className="w-5 h-5" />
                          뉴스 저장
                        </>
                      )}
                    </button>

                    <button
                      onClick={() => {
                        setNewsTitle('');
                        setNewsContent('');
                        setSaveMessage(null);
                      }}
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
              {newsList.length > 0 && (
                <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                  <h3 className="text-lg font-semibold text-white mb-4">최근 작성한 뉴스</h3>
                  <div className="space-y-3">
                    {newsList.filter(n => n.category === selectedCategory).map((news) => (
                      <div
                        key={news.id}
                        className="p-4 bg-slate-900/50 rounded-xl border border-slate-700/30"
                      >
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <h4 className="text-white font-medium">{news.title}</h4>
                            <p className="text-slate-400 text-sm mt-1 line-clamp-2">{news.content}</p>
                            <p className="text-slate-500 text-xs mt-2">
                              {new Date(news.created_at || '').toLocaleString('ko-KR')}
                            </p>
                          </div>
                          <button
                            onClick={() => setNewsList(prev => prev.filter(n => n.id !== news.id))}
                            className="text-red-400 hover:text-red-300 text-sm"
                          >
                            삭제
                          </button>
                        </div>
                      </div>
                    ))}
                    {newsList.filter(n => n.category === selectedCategory).length === 0 && (
                      <p className="text-slate-500 text-center py-4">
                        이 카테고리에 작성된 뉴스가 없습니다.
                      </p>
                    )}
                  </div>
                </div>
              )}
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

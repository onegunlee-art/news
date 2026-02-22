/**
 * Admin Dashboard - 기본 관리자 기능
 * 대시보드, 회원 관리, 개인정보처리방침, 설정, 뉴스 관리
 */
import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import {
  ChartBarIcon,
  UsersIcon,
  NewspaperIcon,
  CogIcon,
  DocumentTextIcon,
  ArrowLeftIcon,
  XMarkIcon,
} from '@heroicons/react/24/outline'
import { api } from '../services/api'
import { adminSettingsApi } from '../services/api'
import { PRIVACY_POLICY_CONTENT } from '../components/Common/PrivacyPolicyContent'

type TabId = 'dashboard' | 'members' | 'privacy' | 'settings' | 'news'

interface Stats {
  totalUsers: number
  totalNews: number
  totalAnalyses: number
  todayUsers: number
  todayAnalyses: number
}

interface User {
  id: number
  email: string | null
  nickname: string
  profile_image: string | null
  role: string
  status: string
  last_login_at: string | null
  created_at: string
  usage?: {
    analyses_count: number
    bookmarks_count: number
    search_count: number
  }
}

interface NewsItem {
  id: number
  category: string
  title: string
  source: string | null
  created_at: string
}

const TABS: { id: TabId; name: string; icon: React.ElementType }[] = [
  { id: 'dashboard', name: '대시보드', icon: ChartBarIcon },
  { id: 'members', name: '회원 관리', icon: UsersIcon },
  { id: 'privacy', name: '개인정보처리방침', icon: DocumentTextIcon },
  { id: 'settings', name: '설정', icon: CogIcon },
  { id: 'news', name: '뉴스 관리', icon: NewspaperIcon },
]

export default function AdminDashboard() {
  const navigate = useNavigate()
  const { user, isAuthenticated } = useAuthStore()
  const [activeTab, setActiveTab] = useState<TabId>('dashboard')
  const [stats, setStats] = useState<Stats | null>(null)
  const [users, setUsers] = useState<User[]>([])
  const [usersPage, setUsersPage] = useState(1)
  const [usersTotal, setUsersTotal] = useState(0)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)
  const [newsList, setNewsList] = useState<NewsItem[]>([])
  const [newsPage, setNewsPage] = useState(1)
  const [newsTotal, setNewsTotal] = useState(0)
  const [privacyContent, setPrivacyContent] = useState('')
  const [termsContent, setTermsContent] = useState('')
  const [settings, setSettings] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  // Admin 체크
  useEffect(() => {
    if (!isAuthenticated || !user) {
      navigate('/login')
      return
    }
    if (user.role !== 'admin') {
      navigate('/')
    }
  }, [user, isAuthenticated, navigate])

  // 초기 stats 로드
  useEffect(() => {
    api.get<{ success: boolean; data: Stats }>('/admin/stats')
      .then((r) => r.data?.success && r.data?.data && setStats(r.data.data))
      .catch(() => {})
  }, [])

  // 회원 목록
  useEffect(() => {
    if (activeTab !== 'members') return
    setLoading(true)
    api.get<{ success: boolean; data: { items: User[]; pagination: { total: number } } }>(`/admin/users?page=${usersPage}&per_page=20`)
      .then((r) => {
        if (r.data?.success && r.data?.data) {
          setUsers(r.data.data.items || [])
          setUsersTotal(r.data.data.pagination?.total ?? 0)
        }
      })
      .catch(() => setUsers([]))
      .finally(() => setLoading(false))
  }, [activeTab, usersPage])

  // 회원 상세 (선택 시)
  useEffect(() => {
    if (!selectedUser) return
    api.get<{ success: boolean; data: User }>(`/admin/users/${selectedUser.id}`)
      .then((r) => r.data?.success && r.data?.data && setSelectedUser(r.data.data))
      .catch(() => {})
  }, [selectedUser?.id])

  // 뉴스 목록
  useEffect(() => {
    if (activeTab !== 'news') return
    setLoading(true)
    api.get<{ success: boolean; data: { items: NewsItem[]; pagination: { total: number } } }>(`/admin/news?page=${newsPage}&per_page=20`)
      .then((r) => {
        if (r.data?.success && r.data?.data) {
          setNewsList(r.data.data.items || [])
          setNewsTotal(r.data.data.pagination?.total ?? 0)
        }
      })
      .catch(() => setNewsList([]))
      .finally(() => setLoading(false))
  }, [activeTab, newsPage])

  // 개인정보처리방침 / 설정 로드
  useEffect(() => {
    if (activeTab !== 'privacy' && activeTab !== 'settings') return
    setLoading(true)
    adminSettingsApi.getSettings()
      .then((r) => {
        if (r.data?.success && r.data?.data) {
          const d = r.data.data as Record<string, string>
          setSettings(d)
          setPrivacyContent(d.privacy_policy || PRIVACY_POLICY_CONTENT)
          setTermsContent(d.terms_of_service || '')
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [activeTab])

  const handleSavePrivacy = async () => {
    setSaving(true)
    setMessage(null)
    try {
      await adminSettingsApi.updateSettings({ privacy_policy: privacyContent })
      setMessage({ type: 'success', text: '개인정보처리방침이 저장되었습니다.' })
    } catch {
      setMessage({ type: 'error', text: '저장에 실패했습니다.' })
    } finally {
      setSaving(false)
    }
  }

  const handleSaveTerms = async () => {
    setSaving(true)
    setMessage(null)
    try {
      await adminSettingsApi.updateSettings({ terms_of_service: termsContent })
      setMessage({ type: 'success', text: '이용약관이 저장되었습니다.' })
    } catch {
      setMessage({ type: 'error', text: '저장에 실패했습니다.' })
    } finally {
      setSaving(false)
    }
  }

  const handleSaveSettings = async () => {
    setSaving(true)
    setMessage(null)
    try {
      await adminSettingsApi.updateSettings(settings)
      setMessage({ type: 'success', text: '설정이 저장되었습니다.' })
    } catch {
      setMessage({ type: 'error', text: '저장에 실패했습니다.' })
    } finally {
      setSaving(false)
    }
  }

  const handleUserStatus = async (userId: number, status: string) => {
    try {
      await api.put(`/admin/users/${userId}/status`, { status })
      setUsers((prev) => prev.map((u) => (u.id === userId ? { ...u, status } : u)))
      setSelectedUser((prev) => (prev?.id === userId ? { ...prev, status } : prev))
      setMessage({ type: 'success', text: '상태가 변경되었습니다.' })
    } catch {
      setMessage({ type: 'error', text: '상태 변경에 실패했습니다.' })
    }
  }

  if (!user || user.role !== 'admin') return null

  return (
    <div className="min-h-screen bg-slate-900">
      <div className="flex">
        {/* Sidebar */}
        <div className="w-56 min-h-screen bg-slate-800/50 border-r border-slate-700 p-4">
          <h1 className="text-lg font-bold text-white mb-6">Admin</h1>
          <nav className="space-y-1">
            {TABS.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm ${
                  activeTab === tab.id ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white'
                }`}
              >
                <tab.icon className="w-4 h-4" />
                {tab.name}
              </button>
            ))}
          </nav>
          <button onClick={() => navigate('/')} className="mt-8 flex items-center gap-2 text-slate-400 hover:text-white text-sm">
            <ArrowLeftIcon className="w-4 h-4" />
            홈으로
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 p-6 overflow-auto">
          {message && (
            <div className={`mb-4 px-4 py-2 rounded ${message.type === 'success' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'}`}>
              {message.text}
            </div>
          )}

          {activeTab === 'dashboard' && (
            <div>
              <h2 className="text-xl font-bold text-white mb-4">대시보드</h2>
              {loading ? (
                <div className="text-slate-400">로딩 중...</div>
              ) : stats ? (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="bg-slate-800 rounded-lg p-4">
                    <p className="text-slate-400 text-sm">전체 사용자</p>
                    <p className="text-2xl font-bold text-white">{stats.totalUsers}</p>
                  </div>
                  <div className="bg-slate-800 rounded-lg p-4">
                    <p className="text-slate-400 text-sm">저장된 뉴스</p>
                    <p className="text-2xl font-bold text-white">{stats.totalNews.toLocaleString()}</p>
                  </div>
                  <div className="bg-slate-800 rounded-lg p-4">
                    <p className="text-slate-400 text-sm">오늘 가입</p>
                    <p className="text-2xl font-bold text-white">{stats.todayUsers}</p>
                  </div>
                  <div className="bg-slate-800 rounded-lg p-4">
                    <p className="text-slate-400 text-sm">오늘 분석</p>
                    <p className="text-2xl font-bold text-white">{stats.todayAnalyses}</p>
                  </div>
                </div>
              ) : (
                <p className="text-slate-400">통계를 불러올 수 없습니다.</p>
              )}
            </div>
          )}

          {activeTab === 'members' && (
            <div>
              <h2 className="text-xl font-bold text-white mb-4">회원 관리</h2>
              {loading ? (
                <div className="text-slate-400">로딩 중...</div>
              ) : (
                <div className="bg-slate-800 rounded-lg overflow-hidden">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-slate-700">
                        <th className="text-left p-3 text-slate-400">닉네임</th>
                        <th className="text-left p-3 text-slate-400">이메일</th>
                        <th className="text-left p-3 text-slate-400">역할</th>
                        <th className="text-left p-3 text-slate-400">상태</th>
                        <th className="text-left p-3 text-slate-400">가입일</th>
                        <th className="text-left p-3 text-slate-400">마지막 로그인</th>
                        <th className="p-3"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {users.map((u) => (
                        <tr key={u.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                          <td className="p-3 text-white">{u.nickname}</td>
                          <td className="p-3 text-slate-300">{u.email || '-'}</td>
                          <td className="p-3 text-slate-300">{u.role}</td>
                          <td className="p-3">
                            <span className={`px-2 py-0.5 rounded text-xs ${u.status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : u.status === 'banned' ? 'bg-red-500/20 text-red-300' : 'bg-slate-500/20 text-slate-400'}`}>
                              {u.status}
                            </span>
                          </td>
                          <td className="p-3 text-slate-400">{new Date(u.created_at).toLocaleDateString('ko-KR')}</td>
                          <td className="p-3 text-slate-400">{u.last_login_at ? new Date(u.last_login_at).toLocaleString('ko-KR') : '-'}</td>
                          <td className="p-3">
                            <button onClick={() => setSelectedUser(u)} className="text-cyan-400 hover:underline text-xs">
                              상세
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {usersTotal > 20 && (
                    <div className="p-3 flex gap-2">
                      <button disabled={usersPage <= 1} onClick={() => setUsersPage((p) => p - 1)} className="px-3 py-1 rounded bg-slate-700 text-slate-300 disabled:opacity-50">
                        이전
                      </button>
                      <button disabled={usersPage * 20 >= usersTotal} onClick={() => setUsersPage((p) => p + 1)} className="px-3 py-1 rounded bg-slate-700 text-slate-300 disabled:opacity-50">
                        다음
                      </button>
                    </div>
                  )}
                </div>
              )}

              {/* 회원 상세 모달 */}
              {selectedUser && (
                <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onClick={() => setSelectedUser(null)}>
                  <div className="bg-slate-800 rounded-xl p-6 max-w-md w-full mx-4" onClick={(e) => e.stopPropagation()}>
                    <div className="flex justify-between items-center mb-4">
                      <h3 className="text-lg font-bold text-white">회원 상세</h3>
                      <button onClick={() => setSelectedUser(null)} className="text-slate-400 hover:text-white">
                        <XMarkIcon className="w-5 h-5" />
                      </button>
                    </div>
                    <div className="space-y-2 text-sm">
                      <p><span className="text-slate-400">닉네임:</span> <span className="text-white">{selectedUser.nickname}</span></p>
                      <p><span className="text-slate-400">이메일:</span> <span className="text-white">{selectedUser.email || '-'}</span></p>
                      <p><span className="text-slate-400">가입일:</span> <span className="text-white">{new Date(selectedUser.created_at).toLocaleString('ko-KR')}</span></p>
                      <p><span className="text-slate-400">마지막 로그인:</span> <span className="text-white">{selectedUser.last_login_at ? new Date(selectedUser.last_login_at).toLocaleString('ko-KR') : '-'}</span></p>
                      {selectedUser.usage && (
                        <div className="mt-4 pt-4 border-t border-slate-700">
                          <p className="text-slate-400 mb-2">사용 통계</p>
                          <p className="text-white">분석 횟수: {selectedUser.usage.analyses_count}회</p>
                          <p className="text-white">북마크: {selectedUser.usage.bookmarks_count}개</p>
                          <p className="text-white">검색: {selectedUser.usage.search_count}회</p>
                        </div>
                      )}
                    </div>
                    <div className="mt-4 flex gap-2">
                      <select
                        value={selectedUser.status}
                        onChange={(e) => handleUserStatus(selectedUser.id, e.target.value)}
                        className="bg-slate-700 text-white rounded px-3 py-1 text-sm"
                      >
                        <option value="active">active</option>
                        <option value="inactive">inactive</option>
                        <option value="banned">banned</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === 'privacy' && (
            <div>
              <h2 className="text-xl font-bold text-white mb-4">개인정보처리방침 수정</h2>
              {loading ? (
                <div className="text-slate-400">로딩 중...</div>
              ) : (
                <div className="space-y-4">
                  <div>
                    <label className="block text-slate-400 text-sm mb-2">개인정보처리방침</label>
                    <textarea
                      value={privacyContent}
                      onChange={(e) => setPrivacyContent(e.target.value)}
                      className="w-full h-64 bg-slate-800 text-white rounded-lg p-4 text-sm font-mono"
                      placeholder="개인정보처리방침 전문"
                    />
                    <button onClick={handleSavePrivacy} disabled={saving} className="mt-2 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-500 disabled:opacity-50">
                      {saving ? '저장 중...' : '저장'}
                    </button>
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-2">이용약관</label>
                    <textarea
                      value={termsContent}
                      onChange={(e) => setTermsContent(e.target.value)}
                      className="w-full h-48 bg-slate-800 text-white rounded-lg p-4 text-sm font-mono"
                      placeholder="이용약관 전문"
                    />
                    <button onClick={handleSaveTerms} disabled={saving} className="mt-2 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-500 disabled:opacity-50">
                      {saving ? '저장 중...' : '저장'}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === 'settings' && (
            <div>
              <h2 className="text-xl font-bold text-white mb-4">설정</h2>
              {loading ? (
                <div className="text-slate-400">로딩 중...</div>
              ) : (
                <div className="space-y-4 max-w-md">
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">사이트 이름</label>
                    <input
                      type="text"
                      value={settings.site_name || ''}
                      onChange={(e) => setSettings((s) => ({ ...s, site_name: e.target.value }))}
                      className="w-full bg-slate-800 text-white rounded-lg px-4 py-2"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">유지보수 모드</label>
                    <select
                      value={settings.maintenance_mode || 'false'}
                      onChange={(e) => setSettings((s) => ({ ...s, maintenance_mode: e.target.value }))}
                      className="w-full bg-slate-800 text-white rounded-lg px-4 py-2"
                    >
                      <option value="false">해제</option>
                      <option value="true">활성화</option>
                    </select>
                  </div>
                  <button onClick={handleSaveSettings} disabled={saving} className="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-500 disabled:opacity-50">
                    {saving ? '저장 중...' : '저장'}
                  </button>
                </div>
              )}
            </div>
          )}

          {activeTab === 'news' && (
            <div>
              <h2 className="text-xl font-bold text-white mb-4">뉴스 관리</h2>
              <p className="text-slate-400 text-sm mb-4">뉴스 목록입니다. 상세 편집은 기존 Admin 뉴스 편집기를 사용하세요.</p>
              {loading ? (
                <div className="text-slate-400">로딩 중...</div>
              ) : (
                <div className="bg-slate-800 rounded-lg overflow-hidden">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-slate-700">
                        <th className="text-left p-3 text-slate-400">제목</th>
                        <th className="text-left p-3 text-slate-400">카테고리</th>
                        <th className="text-left p-3 text-slate-400">출처</th>
                        <th className="text-left p-3 text-slate-400">등록일</th>
                        <th className="p-3"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {newsList.map((n) => (
                        <tr key={n.id} className="border-b border-slate-700/50 hover:bg-slate-700/30">
                          <td className="p-3 text-white line-clamp-1">{n.title}</td>
                          <td className="p-3 text-slate-300">{n.category}</td>
                          <td className="p-3 text-slate-300">{n.source || '-'}</td>
                          <td className="p-3 text-slate-400">{new Date(n.created_at).toLocaleDateString('ko-KR')}</td>
                          <td className="p-3">
                            <a href={`/news/${n.id}`} target="_blank" rel="noopener noreferrer" className="text-cyan-400 hover:underline text-xs flex items-center gap-1">
                              보기
                            </a>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {newsTotal > 20 && (
                    <div className="p-3 flex gap-2">
                      <button disabled={newsPage <= 1} onClick={() => setNewsPage((p) => p - 1)} className="px-3 py-1 rounded bg-slate-700 text-slate-300 disabled:opacity-50">
                        이전
                      </button>
                      <button disabled={newsPage * 20 >= newsTotal} onClick={() => setNewsPage((p) => p + 1)} className="px-3 py-1 rounded bg-slate-700 text-slate-300 disabled:opacity-50">
                        다음
                      </button>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

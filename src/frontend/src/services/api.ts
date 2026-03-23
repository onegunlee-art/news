import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'

/** Admin API용 fetch 래퍼 - Authorization 헤더 자동 첨부 + 401 시 토큰 갱신 재시도 */
export async function adminFetch(url: string, init?: RequestInit): Promise<Response> {
  const method = (init?.method || 'GET').toUpperCase()

  const buildHeaders = (token: string | null) => {
    const headers = new Headers(init?.headers)
    headers.set('Content-Type', 'application/json')
    if (method === 'GET') {
      headers.set('Cache-Control', 'no-cache')
      headers.set('Pragma', 'no-cache')
    }
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
      headers.set('X-Authorization', `Bearer ${token}`)
    }
    return headers
  }

  const token = typeof localStorage !== 'undefined' ? localStorage.getItem('access_token') : null
  const response = await fetch(url, {
    ...init,
    headers: buildHeaders(token),
    ...(method === 'GET' ? { cache: 'no-store' as RequestCache } : {}),
  })

  if (response.status === 401) {
    const refreshToken = typeof localStorage !== 'undefined' ? localStorage.getItem('refresh_token') : null
    if (refreshToken) {
      try {
        const refreshRes = await fetch(`${API_BASE_URL}/auth/refresh`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ refresh_token: refreshToken }),
        })
        const refreshData = await refreshRes.json()
        if (refreshData.success && refreshData.data) {
          const { access_token, refresh_token: newRefresh } = refreshData.data
          localStorage.setItem('access_token', access_token)
          localStorage.setItem('refresh_token', newRefresh)
          try {
            const { useAuthStore } = await import('../store/authStore')
            useAuthStore.getState().setTokens(access_token, newRefresh)
          } catch { /* store not ready */ }
          return fetch(url, {
            ...init,
            headers: buildHeaders(access_token),
            ...(method === 'GET' ? { cache: 'no-store' as RequestCache } : {}),
          })
        }
      } catch { /* refresh failed */ }
    }
  }

  return response
}

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// 요청 인터셉터 (Authorization + X-Authorization: 일부 호스팅에서 Authorization 헤더가 제거되는 경우 대비)
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token')
    if (token) {
      const bearer = `Bearer ${token}`
      config.headers.Authorization = bearer
      config.headers['X-Authorization'] = bearer
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 응답 인터셉터: 401 시 토큰 갱신 (단일 책임 — store와 localStorage 모두 갱신)
let isRefreshing = false
let isForceLoggingOut = false
let failedQueue: { resolve: (v: unknown) => void; reject: (e: unknown) => void }[] = []

function processQueue(error: unknown, token: string | null) {
  failedQueue.forEach((p) => (error ? p.reject(error) : p.resolve(token)))
  failedQueue = []
}

async function forceLogoutOnce() {
  if (isForceLoggingOut) return
  isForceLoggingOut = true

  localStorage.removeItem('access_token')
  localStorage.removeItem('refresh_token')
  localStorage.removeItem('user')
  localStorage.removeItem('is_subscribed')

  try {
    const { useAuthStore } = await import('../store/authStore')
    useAuthStore.setState({
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,
      user: null,
      isSubscribed: false,
      isInitialized: true,
    })
  } catch { /* store not available */ }

  localStorage.removeItem('auth-storage')

  setTimeout(() => { isForceLoggingOut = false }, 2000)
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config
    if (error.response?.status !== 401 || originalRequest._retry) {
      return Promise.reject(error)
    }

    if (isForceLoggingOut) {
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        failedQueue.push({ resolve, reject })
      }).then((token) => {
        const bearer = `Bearer ${token}`
        originalRequest.headers.Authorization = bearer
        originalRequest.headers['X-Authorization'] = bearer
        return api(originalRequest)
      })
    }

    originalRequest._retry = true
    isRefreshing = true

    const refreshToken = localStorage.getItem('refresh_token')
    if (!refreshToken) {
      isRefreshing = false
      return Promise.reject(error)
    }

    try {
      const res = await axios.post(`${API_BASE_URL}/auth/refresh`, {
        refresh_token: refreshToken,
      })

      if (res.data.success) {
        const { access_token, refresh_token: newRefresh } = res.data.data
        localStorage.setItem('access_token', access_token)
        localStorage.setItem('refresh_token', newRefresh)

        try {
          const { useAuthStore } = await import('../store/authStore')
          useAuthStore.getState().setTokens(access_token, newRefresh)
        } catch { /* store not ready */ }

        processQueue(null, access_token)

        const bearer = `Bearer ${access_token}`
        originalRequest.headers.Authorization = bearer
        originalRequest.headers['X-Authorization'] = bearer
        return api(originalRequest)
      }

      processQueue(error, null)
      forceLogoutOnce()
    } catch (refreshError: unknown) {
      processQueue(refreshError, null)
      const hasServerResponse = axios.isAxiosError(refreshError) && refreshError.response != null
      if (hasServerResponse) {
        forceLogoutOnce()
      }
    } finally {
      isRefreshing = false
    }

    return Promise.reject(error)
  }
)

// API 함수들
export const newsApi = {
  // 우리 DB에서 뉴스 목록 조회 (published_only: 유저용 목록에서 draft 제외)
  getList: (page = 1, perPage = 20, category?: string, publishedOnly = true) =>
    api.get('/admin/news.php', {
      params: {
        page,
        per_page: perPage,
        category,
        ...(publishedOnly && { published_only: '1' }),
      },
    }),
  
  // 우리 DB에서 키워드 검색 (유저용, published만)
  search: (query: string, page = 1, perPage = 20) =>
    api.get('/admin/news.php', {
      params: { query, page, per_page: perPage, published_only: '1' },
    }),
  
  getDetail: (id: number, params?: Record<string, unknown>) =>
    api.get('/news/detail.php', { params: { id, ...params } }),
  
  bookmark: (id: number, memo?: string) =>
    api.post('/news/bookmark', { id, memo }),
  
  removeBookmark: (id: number) =>
    api.delete('/news/bookmark', { params: { id } }),
  
  getBookmarks: (page = 1, perPage = 20) =>
    api.get('/user/bookmarks', { params: { page, per_page: perPage } }),
  
  /** 가장 많이 조회한 기사 최대 20개 (인기 탭용, 페이지네이션 없음) */
  getPopular: () =>
    api.get('/admin/news.php', {
      params: { published_only: '1', popular: '1', per_page: 20 },
    }),
  
  // NYT API
  nytTop: (section = 'home') =>
    api.get('/news/nyt/top', { params: { section } }),
  
  nytSearch: (query: string, page = 0) =>
    api.get('/news/nyt/search', { params: { q: query, page } }),
  
  nytPopular: (type = 'viewed', period = 1) =>
    api.get('/news/nyt/popular', { params: { type, period } }),
  
  nytSections: () =>
    api.get('/news/nyt/sections'),
}

export const analysisApi = {
  analyzeNews: (newsId: number) =>
    api.post(`/analysis/news/${newsId}`),
  
  analyzeText: (text: string) =>
    api.post('/analysis/text', { text }),
  
  getResult: (id: number) =>
    api.get(`/analysis/${id}`),
  
  getHistory: (page = 1, perPage = 20) =>
    api.get('/analysis/user/history', { params: { page, per_page: perPage } }),
}

/** TTS 생성 (Google 보이스 - Admin 설정 적용) */
export const ttsApi = {
  /** 구조화: 제목 pause 매체설명 pause 내레이션 pause Critique (Listen용) */
  generateStructured: (title: string, meta: string, narration: string, critiquePart: string, newsId?: number) =>
    api.post<{ success: boolean; data: { url: string } }>('/tts/generate', {
      title,
      meta,
      narration,
      critique_part: critiquePart,
      ...(newsId != null && { news_id: newsId }),
    }, { timeout: 810000 }),
  /** 단순 텍스트 (Admin 미리듣기용) */
  generate: (text: string) =>
    api.post<{ success: boolean; data: { url: string } }>('/tts/generate', { text }, { timeout: 810000 }),
}

/** 가입 환영 설정 (공개) */
export const welcomeSettingsApi = {
  getWelcome: () => api.get<{ success: boolean; data: { message: string; title_template: string } }>('/settings/welcome'),
}

/** 사이트 공개 설정 (My Page/푸터: 문의 이메일, 저작권, 비전, 메뉴 설정) */
export const siteSettingsApi = {
  getSite: () =>
    api.get<{
      success: boolean
      data: {
        contact_email: string
        copyright_text: string
        the_gist_vision: string
        menu_tabs?: string
        menu_subcategories?: string
        subscription_plan_details?: string
        subscription_page_intro?: string
        special_badge_text?: string
      }
    }>('/settings/site'),
}

/** 문의하기 (이메일 발송) */
export const contactApi = {
  send: (body: { subject?: string; contact?: string; message: string }) => api.post<{ success: boolean; message?: string }>('/contact', body),
}

/** Admin 설정 (Router: GET/PUT /api/admin/settings) */
export const adminSettingsApi = {
  getSettings: () => api.get<{ success: boolean; data: Record<string, string> }>('/admin/settings'),
  updateSettings: (settings: Record<string, string>) =>
    api.put('/admin/settings', settings),
}

/** Admin TTS 일괄 재생성 (배치 처리로 504 방지) */
export const adminTtsApi = {
  regenerateAll: (params?: { force?: boolean; offset?: number; limit?: number }) =>
    api.post<{ success: boolean; data: { generated: number; skipped: number; total: number; offset: number; has_more: boolean } }>(
      '/admin/tts/regenerate-all',
      params ?? {},
      { timeout: 1080000 }
    ),
}

export const authApi = {
  /** 이메일/비밀번호 로그인 */
  login: (email: string, password: string) =>
    api.post('/auth/login', { email, password }),
  
  /** 이메일 인증 코드 발송 */
  sendVerification: (email: string) =>
    api.post('/auth/send-verification', { email }),
  
  /** 이메일 인증 코드 검증 */
  verifyCode: (email: string, code: string) =>
    api.post('/auth/verify-code', { email, code }),
  
  /** 이메일/비밀번호 회원가입 (이메일 인증 완료 후) */
  register: (email: string, password: string, nickname: string) =>
    api.post('/auth/register', { email, password, nickname }),
  
  getMe: () =>
    api.get('/auth/me'),
  
  refresh: (refreshToken: string) =>
    api.post('/auth/refresh', { refresh_token: refreshToken }),
  
  logout: (refreshToken?: string) =>
    api.post('/auth/logout', { refresh_token: refreshToken }),
}

/** 구독 관리 (상세 조회, 자동 갱신 토글, 취소) */
export type SubscriptionDetail = {
  plan_name: string
  status: string
  status_label: string
  start_date: string | null
  next_payment_date: string | null
  amount_formatted: string
  auto_renew: boolean
  subscription_id: number | null
}

export const subscriptionApi = {
  getDetail: () =>
    api.get<{ success: boolean; data: SubscriptionDetail }>('/subscription/detail'),
  setAutoRenew: (enabled: boolean) =>
    api.post<{ success: boolean; message?: string }>('/subscription/auto-renew', { enabled }),
  cancel: () =>
    api.post<{ success: boolean; message?: string }>('/subscription/cancel'),
}


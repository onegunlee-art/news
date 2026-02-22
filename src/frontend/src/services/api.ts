import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'

/** Admin API용 fetch 래퍼 - Authorization 헤더 자동 첨부 (향후 인증 적용 시 대비) */
export function adminFetch(url: string, init?: RequestInit): Promise<Response> {
  const token = typeof localStorage !== 'undefined' ? localStorage.getItem('access_token') : null
  const headers = new Headers(init?.headers)
  headers.set('Content-Type', 'application/json')
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
    headers.set('X-Authorization', `Bearer ${token}`)
  }
  return fetch(url, { ...init, headers })
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

// 응답 인터셉터
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    // 401 에러이고 재시도하지 않은 경우
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      const refreshToken = localStorage.getItem('refresh_token')
      if (refreshToken) {
        try {
          const response = await axios.post(`${API_BASE_URL}/auth/refresh`, {
            refresh_token: refreshToken,
          })

          if (response.data.success) {
            const { access_token, refresh_token } = response.data.data
            localStorage.setItem('access_token', access_token)
            localStorage.setItem('refresh_token', refresh_token)

            const bearer = `Bearer ${access_token}`
            originalRequest.headers.Authorization = bearer
            originalRequest.headers['X-Authorization'] = bearer
            return api(originalRequest)
          }
        } catch (refreshError) {
          // 리프레시 실패 시 로그아웃 처리
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')
          localStorage.removeItem('user')
          window.location.href = '/'
        }
      }
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
  
  /** 이메일/비밀번호 회원가입 */
  register: (email: string, password: string, nickname: string) =>
    api.post('/auth/register', { email, password, nickname }),
  
  getMe: () =>
    api.get('/auth/me'),
  
  refresh: (refreshToken: string) =>
    api.post('/auth/refresh', { refresh_token: refreshToken }),
  
  logout: (refreshToken?: string) =>
    api.post('/auth/logout', { refresh_token: refreshToken }),
}

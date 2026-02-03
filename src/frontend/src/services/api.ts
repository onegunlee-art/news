import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api'

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// 요청 인터셉터
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
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

            originalRequest.headers.Authorization = `Bearer ${access_token}`
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
  // 우리 DB에서 뉴스 목록 조회
  getList: (page = 1, perPage = 20, category?: string) =>
    api.get('/admin/news.php', { params: { page, per_page: perPage, category } }),
  
  // 우리 DB에서 키워드 검색
  search: (query: string, page = 1, perPage = 20) =>
    api.get('/admin/news.php', { params: { query, page, per_page: perPage } }),
  
  getDetail: (id: number) =>
    api.get('/news/detail.php', { params: { id } }),
  
  bookmark: (id: number, memo?: string) =>
    api.post(`/news/${id}/bookmark`, { memo }),
  
  removeBookmark: (id: number) =>
    api.delete(`/news/${id}/bookmark`),
  
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

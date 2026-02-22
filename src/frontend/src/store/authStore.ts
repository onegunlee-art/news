import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { api } from '../services/api'
import { kakaoLogin as kakaoLoginService, kakaoLogout } from '../services/kakaoAuth'

interface User {
  id: number
  nickname: string
  email: string | null
  profile_image: string | null
  role: string
  created_at: string
  is_subscribed?: boolean
  subscription_expires_at?: string | null
}

interface AuthState {
  user: User | null
  accessToken: string | null
  refreshToken: string | null
  isAuthenticated: boolean
  isSubscribed: boolean
  isLoading: boolean
  isInitialized: boolean
  error: string | null
  
  // Actions
  setUser: (user: User | null) => void
  setTokens: (accessToken: string, refreshToken: string) => void
  login: () => void
  logout: () => Promise<void>
  refreshAccessToken: () => Promise<boolean>
  initializeAuth: () => void
  fetchUser: () => Promise<void>
  setSubscribed: (isSubscribed: boolean) => void
  checkSubscription: () => boolean
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,
      isSubscribed: false,
      isLoading: false,
      isInitialized: false,
      error: null,

      setUser: (user) => {
        set({ user, isAuthenticated: !!user })
      },

      setTokens: (accessToken, refreshToken) => {
        set({ accessToken, refreshToken, isAuthenticated: true })
        localStorage.setItem('access_token', accessToken)
        localStorage.setItem('refresh_token', refreshToken)
      },

      login: async () => {
        // 카카오 JavaScript SDK를 사용한 로그인
        await kakaoLoginService()
      },

      logout: async () => {
        const { accessToken, refreshToken } = get()
        
        try {
          // 카카오 SDK 로그아웃
          await kakaoLogout()
          
          if (accessToken) {
            await api.post('/auth/logout', { refresh_token: refreshToken }, {
              headers: { Authorization: `Bearer ${accessToken}` }
            }).catch(() => {}) // 백엔드 로그아웃 실패해도 계속 진행
          }
        } catch (error) {
          console.error('Logout error:', error)
        } finally {
          // 로컬 상태 초기화
          set({
            user: null,
            accessToken: null,
            refreshToken: null,
            isAuthenticated: false,
            isSubscribed: false,
            isInitialized: true,
            error: null,
          })
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')
          localStorage.removeItem('user')
          localStorage.removeItem('is_subscribed')
        }
      },

      refreshAccessToken: async () => {
        const { refreshToken } = get()
        
        if (!refreshToken) {
          return false
        }

        try {
          const response = await api.post('/auth/refresh', {
            refresh_token: refreshToken,
          })

          if (response.data.success) {
            const { access_token, refresh_token } = response.data.data
            set({
              accessToken: access_token,
              refreshToken: refresh_token,
            })
            localStorage.setItem('access_token', access_token)
            localStorage.setItem('refresh_token', refresh_token)
            return true
          }
        } catch (error) {
          console.error('Token refresh failed:', error)
          // 리프레시 실패 시 로그아웃
          get().logout()
        }

        return false
      },

      initializeAuth: () => {
        const accessToken = localStorage.getItem('access_token')
        const refreshToken = localStorage.getItem('refresh_token')
        const userStr = localStorage.getItem('user')

        if (accessToken && refreshToken) {
          set({
            accessToken,
            refreshToken,
            isAuthenticated: true,
            user: userStr ? JSON.parse(userStr) : null,
            isInitialized: true,
          })
          
          // 사용자 정보 갱신
          get().fetchUser()
        } else {
          set({ isInitialized: true })
        }
      },

      fetchUser: async () => {
        const { accessToken } = get()
        
        if (!accessToken) {
          return
        }

        set({ isLoading: true, error: null })

        try {
          const response = await api.get('/auth/me', {
            headers: { Authorization: `Bearer ${accessToken}` }
          })

          if (response.data.success) {
            const user = response.data.data
            const isSubscribed = user.is_subscribed || false
            set({ user, isLoading: false, isSubscribed })
            localStorage.setItem('user', JSON.stringify(user))
            localStorage.setItem('is_subscribed', String(isSubscribed))
          }
        } catch (error: any) {
          if (error.response?.status === 401) {
            // 토큰 만료 시 갱신 시도
            const refreshed = await get().refreshAccessToken()
            if (refreshed) {
              await get().fetchUser()
              return
            }
          }
          set({ isLoading: false, error: 'Failed to fetch user' })
        }
      },

      setSubscribed: (isSubscribed: boolean) => {
        set({ isSubscribed })
        localStorage.setItem('is_subscribed', String(isSubscribed))
      },

      checkSubscription: () => {
        const { user, isSubscribed } = get()
        if (!user) return false
        
        // 구독 만료일 체크
        if (user.subscription_expires_at) {
          const expiresAt = new Date(user.subscription_expires_at)
          return expiresAt > new Date()
        }
        
        return isSubscribed
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        isSubscribed: state.isSubscribed,
      }),
    }
  )
)

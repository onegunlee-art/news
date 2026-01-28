import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import { getAuthCodeFromUrl, getAuthErrorFromUrl } from '../services/kakaoAuth'

export default function AuthCallback() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { setTokens, setUser, initializeAuth } = useAuthStore()
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    handleCallback()
  }, [])

  const handleCallback = async () => {
    // URL에서 에러 확인
    const authError = getAuthErrorFromUrl()
    const errorParam = searchParams.get('error')
    
    if (authError || errorParam) {
      setError(authError?.description || decodeURIComponent(errorParam || '알 수 없는 오류'))
      setTimeout(() => navigate('/'), 3000)
      return
    }

    // URL fragment에서 토큰 확인 (백엔드에서 리다이렉트한 경우)
    const hash = window.location.hash.substring(1)
    if (hash) {
      const params = new URLSearchParams(hash)
      const token = params.get('access_token')
      const refreshToken = params.get('refresh_token')
      
      if (token && refreshToken) {
        localStorage.setItem('access_token', token)
        localStorage.setItem('refresh_token', refreshToken)
        initializeAuth()
        navigate('/')
        return
      }
    }

    // 인가 코드 확인 (카카오 SDK Redirect 방식)
    const code = getAuthCodeFromUrl() || searchParams.get('code')
    
    if (code) {
      try {
        // 백엔드에서 토큰 교환
        const response = await fetch('/api/auth/kakao/token', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ code }),
        })

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({ message: 'Unknown error' }))
          console.error('Token exchange failed:', {
            status: response.status,
            statusText: response.statusText,
            error: errorData,
          })
          throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
        }

        const data = await response.json()
        console.log('Token exchange response:', { success: data.success, hasToken: !!data.access_token })

        if (data.success && data.access_token) {
          localStorage.setItem('access_token', data.access_token)
          localStorage.setItem('refresh_token', data.refresh_token || '')
          
          if (data.user) {
            localStorage.setItem('user', JSON.stringify(data.user))
            setUser(data.user)
          }
          
          setTokens(data.access_token, data.refresh_token || '')
          navigate('/')
          return
        } else {
          console.error('Token exchange failed:', data)
          throw new Error(data.message || '토큰 교환 실패')
        }
      } catch (err: any) {
        console.error('Token exchange error:', {
          error: err,
          message: err.message,
          stack: err.stack,
          code: code ? code.substring(0, 20) + '...' : 'no code',
        })
        setError(err.message || '로그인 처리 중 오류가 발생했습니다.')
        setTimeout(() => navigate('/'), 3000)
        return
      }
    }

    // localStorage에서 토큰 확인
    const accessToken = localStorage.getItem('access_token')
    
    if (accessToken) {
      initializeAuth()
      navigate('/')
    } else {
      setError('로그인에 실패했습니다.')
      setTimeout(() => navigate('/'), 3000)
    }
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-dark-500">
        <div className="text-center">
          <div className="text-red-400 mb-4">
            <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-white mb-2">로그인 실패</h2>
          <p className="text-gray-400 mb-4">{error}</p>
          <p className="text-gray-500 text-sm">잠시 후 홈으로 이동합니다...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-dark-500">
      <div className="text-center">
        <LoadingSpinner size="large" />
        <p className="mt-4 text-gray-400">로그인 처리 중...</p>
      </div>
    </div>
  )
}

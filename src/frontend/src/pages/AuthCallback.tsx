import { useEffect, useState, useCallback } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuthStore } from '../store/authStore'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import MaterialIcon from '../components/Common/MaterialIcon'
import { getAuthCodeFromUrl, getAuthErrorFromUrl } from '../services/kakaoAuth'
import { consumeAuthReturnState, getAuthRedirectTarget } from '../utils/authReturnState'

function syncAuthStorage(accessToken: string, refreshToken: string) {
  try {
    localStorage.setItem('auth-storage', JSON.stringify({
      state: { accessToken, refreshToken, isSubscribed: false },
      version: 0,
    }))
  } catch { /* ignore */ }
}

export default function AuthCallback() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { setTokens, setUser, initializeAuth } = useAuthStore()
  const [error, setError] = useState<string | null>(null)

  const handleCallback = useCallback(async () => {
    // URL에서 에러 확인
    const authError = getAuthErrorFromUrl()
    const errorParam = searchParams.get('error')
    
    if (authError || errorParam) {
      setError(authError?.description || decodeURIComponent(errorParam || '알 수 없는 오류'))
      setTimeout(() => navigate('/'), 3000)
      return
    }

    // URL fragment에서 토큰 확인 (callback.php에서 리다이렉트한 경우)
    // callback.php가 localStorage에 access_token, refresh_token, user를 미리 저장함
    const hash = window.location.hash.substring(1)
    if (hash) {
      const params = new URLSearchParams(hash)
      const token = params.get('access_token')
      const refreshToken = params.get('refresh_token')
      
      if (token && refreshToken) {
        localStorage.setItem('access_token', token)
        localStorage.setItem('refresh_token', refreshToken)
        syncAuthStorage(token, refreshToken)

        const userStr = localStorage.getItem('user')
        let user = null
        if (userStr) {
          try {
            user = JSON.parse(userStr)
            setUser(user)
          } catch { /* ignore parse error */ }
        }

        setTokens(token, refreshToken)

        const saved = consumeAuthReturnState()
        const isAdmin = user?.role === 'admin'
        navigate(getAuthRedirectTarget(saved.returnTo, saved.intent, isAdmin), { replace: true })
        return
      }
    }

    // 인가 코드 확인 (카카오 SDK Redirect 방식)
    const code = getAuthCodeFromUrl() || searchParams.get('code')
    
    if (code) {
      try {
        const response = await fetch('/api/auth/kakao/token', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ code }),
        })

        if (!response.ok) {
          let errorData;
          try {
            errorData = await response.json();
          } catch (e) {
            const text = await response.text();
            errorData = { message: text || 'Unknown error', raw: text };
          }
          
          console.error('Token exchange failed:', {
            status: response.status,
            statusText: response.statusText,
            error: errorData,
            url: response.url,
          })
          
          let errorMessage = errorData.message || `HTTP ${response.status}: ${response.statusText}`;
          if (errorData.error) {
            if (typeof errorData.error === 'string') {
              errorMessage += ` - ${errorData.error}`;
            } else if (errorData.error.error_description) {
              errorMessage += ` - ${errorData.error.error_description}`;
            } else if (errorData.error.error) {
              errorMessage += ` - ${errorData.error.error}`;
            }
          }
          
          throw new Error(errorMessage);
        }

        const data = await response.json()
        console.log('Token exchange response:', { success: data.success, hasToken: !!data.access_token })

        if (data.success && data.access_token) {
          localStorage.setItem('access_token', data.access_token)
          localStorage.setItem('refresh_token', data.refresh_token || '')
          syncAuthStorage(data.access_token, data.refresh_token || '')
          
          if (data.user) {
            localStorage.setItem('user', JSON.stringify(data.user))
            setUser(data.user)
          }
          
          if (data.is_new_user) {
            localStorage.setItem('consent_required', '1')
            const userName = data.user?.nickname || data.user?.email?.split('@')[0] || '회원'
            localStorage.setItem('welcome_popup', JSON.stringify({
              userName,
              ts: Date.now(),
            }))
          }
          
          setTokens(data.access_token, data.refresh_token || '')

          const saved = consumeAuthReturnState()
          const isAdmin = data.user?.role === 'admin'
          navigate(getAuthRedirectTarget(saved.returnTo, saved.intent, isAdmin), { replace: true })
          return
        } else {
          console.error('Token exchange failed:', data)
          throw new Error(data.message || '토큰 교환 실패')
        }
      } catch (err: unknown) {
        const errMsg = err instanceof Error ? err.message : '로그인 처리 중 오류가 발생했습니다.'
        console.error('Token exchange error:', {
          error: err,
          message: errMsg,
          stack: err instanceof Error ? err.stack : undefined,
          code: code ? code.substring(0, 20) + '...' : 'no code',
        })
        setError(errMsg)
        setTimeout(() => navigate('/'), 3000)
        return
      }
    }

    const accessToken = localStorage.getItem('access_token')
    
    if (accessToken) {
      initializeAuth()
      const saved = consumeAuthReturnState()
      navigate(getAuthRedirectTarget(saved.returnTo, saved.intent), { replace: true })
    } else {
      setError('로그인에 실패했습니다.')
      setTimeout(() => navigate('/'), 3000)
    }
  }, [searchParams, navigate, setTokens, setUser, initializeAuth])

  useEffect(() => {
    void handleCallback()
  }, [handleCallback])

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-dark-500">
        <div className="text-center">
          <div className="text-red-400 mb-4">
            <MaterialIcon name="warning" className="w-16 h-16 mx-auto" size={64} />
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

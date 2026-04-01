import { useEffect, useState, useRef } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { api } from '../services/api'
import { AxiosError } from 'axios'
import MaterialIcon from '../components/Common/MaterialIcon'
import GistLogo from '../components/Common/GistLogo'
import { waitForAccessToken } from '../utils/waitForAccessToken'

export default function SubscribeSuccessPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { accessToken, setSubscribed, fetchUser } = useAuthStore()
  const [status, setStatus] = useState<'verifying' | 'success' | 'pending' | 'token_lost' | 'error'>('verifying')
  const [message, setMessage] = useState('결제를 확인 중입니다...')
  const [orderCode, setOrderCode] = useState<string | null>(null)
  const calledRef = useRef(false)

  useEffect(() => {
    if (calledRef.current) return
    calledRef.current = true

    const code = searchParams.get('order_code') || searchParams.get('orderCode')
    setOrderCode(code)

    if (!code) {
      setStatus('error')
      setMessage('결제 정보를 확인할 수 없습니다.')
      return
    }

    const run = async () => {
      // silentRefresh 제거: 인터셉터와 동시 갱신 시 리프레시 토큰 경쟁 → forceLogoutOnce 유발 방지
      let token = localStorage.getItem('access_token') || accessToken || null
      if (!token && localStorage.getItem('refresh_token')) {
        token = await waitForAccessToken(5000)
      }

      if (!token) {
        setStatus('token_lost')
        setMessage('결제는 완료되었습니다. 로그인하시면 구독이 확인됩니다.')
        return
      }

      try {
        const res = await api.post('/subscription/verify', { order_code: code }, {
          timeout: 60000,
        })
        if (res.data?.success) {
          setSubscribed(true)
          fetchUser()
          setStatus('success')
          setMessage('the gist. 의 모든 컨텐츠를 만나세요')
        } else if (res.data?.status === 'pending') {
          setStatus('pending')
          setMessage('결제는 정상 처리되었으며, 잠시 후 자동으로 반영됩니다.')
        } else {
          setStatus('error')
          setMessage(res.data?.message || '결제 확인에 실패했습니다.')
        }
      } catch (err) {
        const axiosErr = err as AxiosError
        const httpStatus = axiosErr?.response?.status

        if (httpStatus && httpStatus >= 500) {
          setStatus('pending')
          setMessage('결제는 정상 처리되었으며, 잠시 후 자동으로 반영됩니다.')
        } else if (httpStatus === 401) {
          setStatus('token_lost')
          setMessage('결제는 완료되었습니다. 로그인하시면 구독이 확인됩니다.')
        } else if (!axiosErr?.response) {
          setStatus('pending')
          setMessage('네트워크 연결이 불안정합니다. 결제는 잠시 후 자동 반영됩니다.')
        } else {
          setStatus('error')
          setMessage('결제 확인 중 오류가 발생했습니다.')
        }
      }
    }

    run()
  }, [searchParams, accessToken, setSubscribed, fetchUser])

  const handleRetryVerify = async () => {
    if (!orderCode) return
    setStatus('verifying')
    setMessage('다시 확인 중입니다...')

    let token = localStorage.getItem('access_token')
    if (!token && localStorage.getItem('refresh_token')) {
      token = await waitForAccessToken(5000)
    }
    if (!token) {
      setStatus('token_lost')
      setMessage('결제는 완료되었습니다. 로그인하시면 구독이 확인됩니다.')
      return
    }

    try {
      const res = await api.post('/subscription/verify', { order_code: orderCode }, {
        timeout: 60000,
      })
      if (res.data?.success) {
        setSubscribed(true)
        fetchUser()
        setStatus('success')
        setMessage('the gist. 의 모든 컨텐츠를 만나세요')
      } else if (res.data?.status === 'pending') {
        setStatus('pending')
        setMessage('결제는 정상 처리되었으며, 잠시 후 자동으로 반영됩니다.')
      } else {
        setStatus('pending')
        setMessage('확인이 지연되고 있습니다. 잠시 후 자동으로 반영됩니다.')
      }
    } catch {
      setStatus('pending')
      setMessage('확인이 지연되고 있습니다. 잠시 후 자동으로 반영됩니다.')
    }
  }

  return (
    <div className="min-h-screen bg-page flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center"
      >
        {status === 'verifying' && (
          <>
            <div className="w-16 h-16 mx-auto mb-6 border-4 border-gray-200 border-t-primary-500 rounded-full animate-spin" />
            <h1 className="text-xl font-bold text-gray-900 mb-2">결제 확인 중</h1>
            <p className="text-gray-500 text-sm">{message}</p>
          </>
        )}

        {status === 'success' && (
          <>
            <div className="w-16 h-16 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
              <MaterialIcon name="check_circle" className="w-8 h-8 text-green-600" size={32} filled />
            </div>
            <h1 className="text-xl font-bold text-gray-900 mb-2">결제 완료!</h1>
            <p className="text-gray-500 text-sm mb-8"><GistLogo as="span" size="inline" link={false} /> 의 모든 컨텐츠를 만나세요</p>
            <button
              onClick={() => navigate('/')}
              className="w-full py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-semibold transition-colors"
            >
              홈으로 이동
            </button>
          </>
        )}

        {status === 'pending' && (
          <>
            <div className="w-16 h-16 mx-auto mb-6 bg-blue-100 rounded-full flex items-center justify-center">
              <MaterialIcon name="check_circle" className="w-8 h-8 text-blue-600" size={32} filled />
            </div>
            <h1 className="text-xl font-bold text-gray-900 mb-2">결제 완료</h1>
            <p className="text-gray-500 text-sm mb-6">{message}</p>
            <div className="flex gap-3">
              <button
                onClick={handleRetryVerify}
                className="flex-1 py-3 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
              >
                다시 확인
              </button>
              <button
                onClick={() => navigate('/')}
                className="flex-1 py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-semibold transition-colors"
              >
                홈으로 이동
              </button>
            </div>
          </>
        )}

        {status === 'token_lost' && (
          <>
            <div className="w-16 h-16 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
              <MaterialIcon name="check_circle" className="w-8 h-8 text-green-600" size={32} filled />
            </div>
            <h1 className="text-xl font-bold text-gray-900 mb-2">결제 완료</h1>
            <p className="text-gray-500 text-sm mb-6">{message}</p>
            <div className="flex flex-col gap-3">
              <button
                type="button"
                onClick={() => void handleRetryVerify()}
                className="w-full py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-semibold transition-colors"
              >
                다시 확인하기
              </button>
              <button
                type="button"
                onClick={() => navigate('/login', { state: { returnTo: orderCode ? `/subscribe/success?order_code=${orderCode}` : '/subscribe/success' } })}
                className="w-full py-3 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
              >
                로그인하여 확인
              </button>
            </div>
          </>
        )}

        {status === 'error' && (
          <>
            <div className="w-16 h-16 mx-auto mb-6 bg-red-100 rounded-full flex items-center justify-center">
              <MaterialIcon name="close" className="w-8 h-8 text-red-600" size={32} />
            </div>
            <h1 className="text-xl font-bold text-gray-900 mb-2">결제 확인 실패</h1>
            <p className="text-gray-500 text-sm mb-8">{message}</p>
            <div className="flex gap-3">
              <button
                onClick={() => navigate('/subscribe')}
                className="flex-1 py-3 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
              >
                다시 시도
              </button>
              <button
                onClick={() => navigate('/')}
                className="flex-1 py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-semibold transition-colors"
              >
                홈으로
              </button>
            </div>
          </>
        )}
      </motion.div>
    </div>
  )
}

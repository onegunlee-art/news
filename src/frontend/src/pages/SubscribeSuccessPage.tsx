import { useEffect, useState, useRef, useCallback } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { api } from '../services/api'
import MaterialIcon from '../components/Common/MaterialIcon'
import GistLogo from '../components/Common/GistLogo'

export default function SubscribeSuccessPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { accessToken, setSubscribed, fetchUser } = useAuthStore()
  const [status, setStatus] = useState<'verifying' | 'success' | 'pending' | 'error'>('verifying')
  const [message, setMessage] = useState('결제를 확인 중입니다...')
  const attemptRef = useRef(0)
  const maxAttempts = 3
  const retryDelay = 4000

  const verifyOrder = useCallback(async (orderCode: string) => {
    try {
      const res = await api.post('/subscription/verify', { order_code: orderCode }, {
        headers: { Authorization: `Bearer ${accessToken}` },
      })
      if (res.data?.success) {
        setSubscribed(true)
        fetchUser()
        setStatus('success')
        setMessage('the gist. 의 모든 컨텐츠를 만나세요')
        return
      }
      if (res.data?.status === 'pending' && attemptRef.current < maxAttempts) {
        attemptRef.current++
        setMessage(`결제 확인 중입니다... (${attemptRef.current}/${maxAttempts})`)
        setTimeout(() => verifyOrder(orderCode), retryDelay)
        return
      }
      if (attemptRef.current >= maxAttempts) {
        setStatus('pending')
        setMessage('결제는 정상 처리되었으며, 잠시 후 자동으로 반영됩니다.')
        return
      }
      setStatus('error')
      setMessage(res.data?.message || '결제 확인에 실패했습니다.')
    } catch {
      if (attemptRef.current < maxAttempts) {
        attemptRef.current++
        setMessage(`결제 확인 중입니다... (${attemptRef.current}/${maxAttempts})`)
        setTimeout(() => verifyOrder(orderCode), retryDelay)
        return
      }
      setStatus('pending')
      setMessage('결제는 정상 처리되었으며, 잠시 후 자동으로 반영됩니다.')
    }
  }, [accessToken, setSubscribed, fetchUser])

  useEffect(() => {
    const orderCode = searchParams.get('order_code') || searchParams.get('orderCode')
    if (!orderCode || !accessToken) {
      setStatus('error')
      setMessage('결제 정보를 확인할 수 없습니다.')
      return
    }
    verifyOrder(orderCode)
  }, [searchParams, accessToken, verifyOrder])

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
            <p className="text-gray-500 text-sm mb-8">{message}</p>
            <button
              onClick={() => navigate('/')}
              className="w-full py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-semibold transition-colors"
            >
              홈으로 이동
            </button>
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

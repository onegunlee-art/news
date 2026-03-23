import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import { api } from '../services/api'
import MaterialIcon from '../components/Common/MaterialIcon'

interface ErrorInfo {
  source: string
  message: string
  order_status: string
}

export default function SubscribeErrorPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [errorInfo, setErrorInfo] = useState<ErrorInfo | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const orderCode = searchParams.get('order_code') || searchParams.get('orderCode')
    if (!orderCode) {
      setErrorInfo({ source: '결제 실패', message: '결제가 완료되지 않았습니다.', order_status: 'unknown' })
      setLoading(false)
      return
    }

    api.get(`/subscription/order-status?order_code=${encodeURIComponent(orderCode)}`)
      .then((res) => {
        if (res.data?.success && res.data?.data) {
          setErrorInfo(res.data.data)
        } else {
          setErrorInfo({ source: '결제 실패', message: '결제가 완료되지 않았습니다.', order_status: 'unknown' })
        }
      })
      .catch(() => {
        setErrorInfo({ source: '결제 실패', message: '결제가 완료되지 않았습니다.', order_status: 'unknown' })
      })
      .finally(() => setLoading(false))
  }, [searchParams])

  const isCanceled = errorInfo?.source === '결제 취소'
  const title = isCanceled ? '결제 취소' : '결제 실패'
  const iconName = isCanceled ? 'close' : 'warning'
  const iconBg = isCanceled ? 'bg-gray-100' : 'bg-red-100'
  const iconColor = isCanceled ? 'text-gray-500' : 'text-red-600'

  return (
    <div className="min-h-screen bg-page flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center"
      >
        {loading ? (
          <div className="py-8">
            <div className="w-12 h-12 mx-auto border-4 border-gray-200 border-t-primary-500 rounded-full animate-spin" />
            <p className="text-gray-500 text-sm mt-4">결제 정보 확인 중...</p>
          </div>
        ) : (
          <>
            <div className={`w-16 h-16 mx-auto mb-6 ${iconBg} rounded-full flex items-center justify-center`}>
              <MaterialIcon name={iconName} className={`w-8 h-8 ${iconColor}`} size={32} />
            </div>
            <h1 className="text-xl font-bold text-gray-900 mb-2">{title}</h1>
            {errorInfo?.source && errorInfo.source !== '결제 실패' && errorInfo.source !== '결제 취소' && (
              <p className="text-xs text-gray-400 mb-1">{errorInfo.source}</p>
            )}
            <p className="text-gray-500 text-sm mb-8">
              {errorInfo?.message || '결제가 완료되지 않았습니다.'}
            </p>
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

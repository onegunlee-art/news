import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import MaterialIcon from '../components/Common/MaterialIcon'

export default function SubscribeErrorPage() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen bg-page flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center"
      >
        <div className="w-16 h-16 mx-auto mb-6 bg-red-100 rounded-full flex items-center justify-center">
          <MaterialIcon name="warning" className="w-8 h-8 text-red-600" size={32} />
        </div>
        <h1 className="text-xl font-bold text-gray-900 mb-2">결제 실패</h1>
        <p className="text-gray-500 text-sm mb-8">
          결제가 완료되지 않았습니다. 다시 시도해 주세요.
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
      </motion.div>
    </div>
  )
}

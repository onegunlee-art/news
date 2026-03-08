import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import MaterialIcon from '../components/Common/MaterialIcon'

export default function NotFoundPage() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-dark-500 bg-gradient-main px-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center"
      >
        <div className="mb-8">
          <span className="text-8xl font-display font-bold bg-gradient-to-r from-primary-400 to-accent-purple bg-clip-text text-transparent">
            404
          </span>
        </div>
        <h1 className="text-2xl font-bold text-white mb-4">
          페이지를 찾을 수 없습니다
        </h1>
        <p className="text-gray-400 mb-8 max-w-md">
          요청하신 페이지가 존재하지 않거나 이동되었을 수 있습니다.
        </p>
        <Link
          to="/"
          className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl transition-all"
        >
          <MaterialIcon name="home" className="w-5 h-5" size={20} />
          홈으로 돌아가기
        </Link>
      </motion.div>
    </div>
  )
}

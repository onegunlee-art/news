import { Link } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import MaterialIcon from './MaterialIcon'
import GistLogo from './GistLogo'

const PLANS = [
  { id: '1m', title: '1개월', priceLabel: '7,700원', periodLabel: '1개월' },
  { id: '3m', title: '3개월', priceLabel: '18,480원', periodLabel: '3개월' },
  { id: '6m', title: '6개월', priceLabel: '32,340원', periodLabel: '6개월', badge: '인기' },
  { id: '12m', title: '12개월', priceLabel: '55,400원', periodLabel: '12개월', badge: '최저가' },
]

interface SubscriptionPopupProps {
  isOpen: boolean
  onClose: () => void
}

export default function SubscriptionPopup({ isOpen, onClose }: SubscriptionPopupProps) {
  if (!isOpen) return null

  return (
    <AnimatePresence>
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        onClick={onClose}
        className="fixed inset-0 z-[200] bg-black/50 backdrop-blur-sm"
      />
      <motion.div
        initial={{ opacity: 0, scale: 0.95, y: 20 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        exit={{ opacity: 0, scale: 0.95, y: 20 }}
        transition={{ duration: 0.2 }}
        className="fixed inset-0 z-[201] flex items-center justify-center p-4 pointer-events-none"
      >
        <div
          className="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden pointer-events-auto"
          onClick={(e) => e.stopPropagation()}
        >
          {/* 헤더: primary 톤 + X */}
          <div className="relative bg-primary-500 px-5 py-4">
            <button
              type="button"
              onClick={onClose}
              className="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors"
              aria-label="닫기"
            >
              <MaterialIcon name="close" className="w-5 h-5" size={20} />
            </button>
            <h2 className="text-lg font-bold text-white pr-8"><GistLogo as="span" size="inline" link={false} className="text-white" /> 구독</h2>
            <p className="text-primary-100 text-sm mt-0.5">기간을 선택하고 무제한으로 이용하세요.</p>
          </div>

          {/* 플랜 4칸 */}
          <div className="p-5">
            <div className="grid grid-cols-2 gap-3">
              {PLANS.map((plan) => (
                <div
                  key={plan.id}
                  className="relative rounded-xl border border-gray-200 bg-gray-50/80 p-3"
                >
                  {plan.badge && (
                    <span className="absolute -top-1.5 left-2 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 bg-primary-100 rounded">
                      {plan.badge}
                    </span>
                  )}
                  <p className="text-sm font-semibold text-gray-900">{plan.title}</p>
                  <p className="text-base font-bold text-primary-600 mt-0.5">{plan.priceLabel}</p>
                  <p className="text-xs text-gray-500">/ {plan.periodLabel}</p>
                </div>
              ))}
            </div>

            <Link
              to="/subscribe"
              onClick={onClose}
              className="mt-5 w-full py-3 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold text-center block transition-colors"
            >
              구독하기
            </Link>
            <p className="text-center text-xs text-gray-400 mt-3">언제든 해지할 수 있습니다.</p>
          </div>
        </div>
      </motion.div>
    </AnimatePresence>
  )
}

import React, { useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'

export interface WelcomePopupProps {
  isOpen: boolean
  onClose: () => void
  userName: string
  welcomeMessage?: string
  promoCode?: string
}

/** 가입 완료 환영 팝업 - 오렌지 브랜드 컬러, 트렌디한 디자인 */
const WelcomePopup: React.FC<WelcomePopupProps> = ({
  isOpen,
  onClose,
  userName,
  welcomeMessage = 'The Gist 가입을 감사드립니다.',
  promoCode,
}) => {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [isOpen])

  const handleCopyCode = () => {
    if (promoCode && navigator.clipboard) {
      navigator.clipboard.writeText(promoCode)
    }
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
          onClick={onClose}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.9, opacity: 0 }}
            transition={{ type: 'spring', damping: 25, stiffness: 300 }}
            className="w-full max-w-md overflow-hidden rounded-2xl shadow-2xl"
            onClick={(e) => e.stopPropagation()}
            style={{
              background: 'linear-gradient(145deg, #f97316 0%, #ea580c 50%, #c2410c 100%)',
              boxShadow: '0 25px 50px -12px rgba(249, 115, 22, 0.35), 0 0 0 1px rgba(255,255,255,0.1)',
            }}
          >
            {/* 내부 카드 - 깊이감 */}
            <div className="relative m-1 rounded-xl bg-white/95 p-8 backdrop-blur">
              {/* 장식 */}
              <div className="absolute -top-2 -right-2 h-24 w-24 rounded-full bg-orange-400/20 blur-2xl" />
              <div className="absolute -bottom-1 -left-1 h-16 w-16 rounded-full bg-amber-400/15 blur-xl" />

              <div className="relative">
                {/* 체크 아이콘 */}
                <div
                  className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full"
                  style={{ background: 'linear-gradient(135deg, #f97316, #ea580c)' }}
                >
                  <svg className="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                </div>

                {/* 이름 */}
                <h2 className="text-center text-xl font-bold text-slate-800">
                  {userName}
                  <span className="font-normal text-slate-600">님</span>
                </h2>

                {/* 환영 메시지 */}
                <p className="mt-3 text-center text-slate-600 leading-relaxed">{welcomeMessage}</p>

                {/* 프로모션 코드 */}
                {promoCode && (
                  <div className="mt-6 rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <p className="mb-2 text-center text-sm font-medium text-slate-500">프로모션 코드</p>
                    <div className="flex items-center justify-center gap-2">
                      <code className="rounded-lg bg-white px-4 py-2 font-mono text-lg font-bold text-orange-600 tracking-wider shadow-inner border border-orange-100">
                        {promoCode}
                      </code>
                      <button
                        type="button"
                        onClick={handleCopyCode}
                        className="rounded-lg bg-orange-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-orange-600"
                      >
                        복사
                      </button>
                    </div>
                  </div>
                )}

                {/* 확인 버튼 */}
                <button
                  type="button"
                  onClick={onClose}
                  className="mt-6 w-full rounded-xl py-3 font-semibold text-white transition"
                  style={{ background: 'linear-gradient(135deg, #f97316, #ea580c)' }}
                >
                  확인
                </button>
              </div>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}

export default WelcomePopup

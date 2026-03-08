import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { api } from '../../services/api'
import { PRIVACY_POLICY_CONTENT } from './PrivacyPolicyContent'
import MaterialIcon from './MaterialIcon'

const DEFAULT_TERMS = `이용약관

주식회사 더지스트(이하 "회사")의 thegist.co.kr(이하 "서비스") 이용약관입니다.

제1조 (목적)
본 약관은 회사가 제공하는 서비스의 이용 조건 및 절차를 규정합니다.

제2조 (약관의 효력)
본 약관은 서비스 화면에 게시하거나 기타의 방법으로 공지함으로써 효력이 발생합니다.

제3조 (서비스의 제공)
회사는 뉴스 분석, AI 기반 콘텐츠 등 서비스를 제공합니다.

제4조 (이용자의 의무)
이용자는 관련 법령 및 본 약관을 준수하여야 합니다.

제5조 (문의)
문의: info@thegist.co.kr`

interface ConsentModalProps {
  isOpen: boolean
  onAgree: () => void
  onCancel: () => void
}

const ConsentModal: React.FC<ConsentModalProps> = ({ isOpen, onAgree, onCancel }) => {
  const [termsContent, setTermsContent] = useState(DEFAULT_TERMS)
  const [privacyContent, setPrivacyContent] = useState(PRIVACY_POLICY_CONTENT)
  const [agreed, setAgreed] = useState(false)
  const [loading, setLoading] = useState(true)
  const [cancelling, setCancelling] = useState(false)

  useEffect(() => {
    if (!isOpen) return
    setLoading(true)
    Promise.all([
      api.get<{ success: boolean; data: { content: string | null } }>('/settings/terms'),
      api.get<{ success: boolean; data: { content: string | null } }>('/settings/privacy'),
    ])
      .then(([termsRes, privacyRes]) => {
        const t = termsRes.data?.data?.content
        const p = privacyRes.data?.data?.content
        if (t && t.trim()) setTermsContent(t)
        if (p && p.trim()) setPrivacyContent(p)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [isOpen])

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/60 z-[100] backdrop-blur-sm"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 24 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 24 }}
            transition={{ duration: 0.25, ease: 'easeOut' }}
            className="fixed inset-0 z-[101] flex items-center justify-center p-4"
          >
            <div
              className="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden"
              onClick={(e) => e.stopPropagation()}
            >
              {/* 헤더 */}
              <div className="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <h2 className="text-xl font-bold text-gray-900">서비스 이용 동의</h2>
                <p className="text-sm text-gray-500 mt-1">
                  서비스 이용을 위해 아래 약관에 동의해 주세요.
                </p>
              </div>

              {/* 약관 내용 */}
              <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5 min-h-0">
                {loading ? (
                  <div className="flex items-center justify-center py-16">
                    <div className="w-8 h-8 border-3 border-gray-200 border-t-primary-500 rounded-full animate-spin" />
                  </div>
                ) : (
                  <>
                    {/* 이용약관 */}
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-900 text-white text-[11px] font-bold">1</span>
                        <h3 className="text-sm font-semibold text-gray-900">이용약관 (필수)</h3>
                      </div>
                      <div className="h-44 overflow-y-auto border border-gray-200 rounded-xl p-4 text-xs text-gray-600 leading-relaxed bg-gray-50/70 whitespace-pre-line scrollbar-thin">
                        {termsContent}
                      </div>
                    </div>

                    {/* 개인정보처리방침 */}
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-900 text-white text-[11px] font-bold">2</span>
                        <h3 className="text-sm font-semibold text-gray-900">개인정보처리방침 (필수)</h3>
                      </div>
                      <div className="h-44 overflow-y-auto border border-gray-200 rounded-xl p-4 text-xs text-gray-600 leading-relaxed bg-gray-50/70 whitespace-pre-line scrollbar-thin">
                        {privacyContent}
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* 동의 체크박스 + 버튼 */}
              <div className="px-6 py-5 border-t border-gray-100 bg-gray-50/50 space-y-4">
                <label className="flex items-start gap-3 cursor-pointer select-none group">
                  <div className="relative mt-0.5">
                    <input
                      type="checkbox"
                      checked={agreed}
                      onChange={(e) => setAgreed(e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-5 h-5 rounded-md border-2 border-gray-300 peer-checked:border-primary-500 peer-checked:bg-primary-500 transition-all duration-150 flex items-center justify-center group-hover:border-gray-400">
                      {agreed && (
                        <MaterialIcon name="check" className="w-3.5 h-3.5 text-white" size={14} filled />
                      )}
                    </div>
                  </div>
                  <span className="text-sm font-medium text-gray-800 leading-snug">
                    이용약관 및 개인정보처리방침에 모두 동의합니다.
                  </span>
                </label>

                <div className="flex gap-3">
                  <button
                    onClick={() => {
                      setCancelling(true)
                      onCancel()
                    }}
                    disabled={cancelling}
                    className="flex-1 py-3 rounded-xl border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 active:bg-gray-200 transition-colors disabled:opacity-50"
                  >
                    {cancelling ? '처리 중...' : '가입 취소'}
                  </button>
                  <button
                    onClick={onAgree}
                    disabled={!agreed || loading}
                    className="flex-1 py-3 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800 active:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  >
                    동의하고 가입
                  </button>
                </div>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}

export default ConsentModal

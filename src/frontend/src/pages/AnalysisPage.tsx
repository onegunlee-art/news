import { useState } from 'react'
import { motion } from 'framer-motion'
import { analysisApi } from '../services/api'
import LoadingSpinner from '../components/Common/LoadingSpinner'
import AnalysisResult from '../components/Analysis/AnalysisResult'

interface AnalysisData {
  id: number
  keywords: Array<{ keyword: string; score: number; count: number }>
  sentiment: {
    type: string
    label: string
    score: number
    color: string
    details: any
  }
  summary: string
  status: string
  processing_time_ms: number
}

export default function AnalysisPage() {
  const [text, setText] = useState('')
  const [analysis, setAnalysis] = useState<AnalysisData | null>(null)
  const [isAnalyzing, setIsAnalyzing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleAnalyze = async () => {
    if (!text.trim() || isAnalyzing) return

    setIsAnalyzing(true)
    setError(null)
    setAnalysis(null)

    try {
      const response = await analysisApi.analyzeText(text)
      if (response.data.success) {
        setAnalysis(response.data.data)
      }
    } catch (error: any) {
      setError(error.response?.data?.message || '분석에 실패했습니다.')
    } finally {
      setIsAnalyzing(false)
    }
  }

  const handleClear = () => {
    setText('')
    setAnalysis(null)
    setError(null)
  }

  const charCount = text.length
  const maxChars = 10000

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center mb-8"
      >
        <h1 className="text-3xl font-bold text-white mb-4">텍스트 분석</h1>
        <p className="text-gray-400">
          분석하고 싶은 뉴스 기사나 텍스트를 입력하세요.
          AI가 키워드 추출, 감정 분석, 요약을 진행합니다.
        </p>
      </motion.div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="card mb-8"
      >
        <div className="relative">
          <textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="분석할 텍스트를 입력하세요... (뉴스 기사, 블로그 글, 리뷰 등)"
            className="w-full h-64 p-4 bg-dark-500 border border-white/10 rounded-xl text-white placeholder-gray-500 resize-none focus:outline-none focus:border-primary-500/50 focus:ring-1 focus:ring-primary-500/50 transition-all"
            maxLength={maxChars}
          />
          <div className="absolute bottom-4 right-4 text-sm text-gray-500">
            {charCount.toLocaleString()} / {maxChars.toLocaleString()}
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-4 mt-4">
          <button
            onClick={handleAnalyze}
            disabled={!text.trim() || isAnalyzing}
            className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-semibold rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isAnalyzing ? (
              <>
                <LoadingSpinner size="small" />
                분석 중...
              </>
            ) : (
              <>
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                AI 분석 시작
              </>
            )}
          </button>

          {(text || analysis) && (
            <button
              onClick={handleClear}
              className="px-4 py-3 text-gray-400 hover:text-white transition-colors"
            >
              초기화
            </button>
          )}
        </div>

        {/* 샘플 텍스트 버튼 */}
        {!text && (
          <div className="mt-4 pt-4 border-t border-white/5">
            <p className="text-sm text-gray-500 mb-2">샘플 텍스트로 시험해보기:</p>
            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => setText('정부가 새로운 경제 활성화 정책을 발표했다. 전문가들은 이번 정책이 경기 회복에 긍정적인 영향을 미칠 것으로 전망했다. 특히 중소기업 지원 확대와 일자리 창출에 초점을 맞춘 점이 높이 평가받고 있다.')}
                className="text-xs px-3 py-1 bg-white/5 hover:bg-white/10 text-gray-400 rounded-full transition-colors"
              >
                경제 뉴스 샘플
              </button>
            </div>
          </div>
        )}
      </motion.div>

      {/* 에러 메시지 */}
      {error && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="mb-8 p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400"
        >
          {error}
        </motion.div>
      )}

      {/* 분석 결과 */}
      {analysis && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <AnalysisResult analysis={analysis} />
        </motion.div>
      )}

      {/* 안내 섹션 */}
      {!analysis && !isAnalyzing && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.3 }}
          className="grid grid-cols-1 md:grid-cols-3 gap-6"
        >
          <InfoCard
            icon="🔑"
            title="키워드 추출"
            description="텍스트에서 핵심 키워드를 자동으로 추출합니다."
          />
          <InfoCard
            icon="💭"
            title="감정 분석"
            description="긍정, 부정, 중립 감정을 분석합니다."
          />
          <InfoCard
            icon="📋"
            title="요약"
            description="텍스트의 핵심 내용을 요약합니다."
          />
        </motion.div>
      )}
    </div>
  )
}

function InfoCard({ icon, title, description }: { icon: string; title: string; description: string }) {
  return (
    <div className="p-6 bg-dark-600/50 rounded-xl border border-white/5 text-center">
      <div className="text-3xl mb-3">{icon}</div>
      <h3 className="font-semibold text-white mb-2">{title}</h3>
      <p className="text-sm text-gray-400">{description}</p>
    </div>
  )
}

import { motion } from 'framer-motion'

interface AnalysisData {
  id: number
  keywords: Array<{ keyword: string; score: number; count: number }>
  sentiment: {
    type: string
    label: string
    score: number
    color: string
    details: {
      positive_count: number
      negative_count: number
      positive_words: Array<{ word: string; count: number }>
      negative_words: Array<{ word: string; count: number }>
    }
  }
  summary: string
  status: string
  processing_time_ms: number
}

interface AnalysisResultProps {
  analysis: AnalysisData
}

export default function AnalysisResult({ analysis }: AnalysisResultProps) {
  const sentimentEmoji = {
    positive: '😊',
    negative: '😟',
    neutral: '😐',
  }[analysis.sentiment.type] || '😐'

  return (
    <div className="space-y-6">
      {/* 요약 */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="card"
      >
        <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
          <span className="text-2xl">📋</span>
          요약
        </h3>
        <p className="text-gray-300 leading-relaxed">
          {analysis.summary || '요약 정보가 없습니다.'}
        </p>
      </motion.div>

      {/* 감정 분석 & 키워드 그리드 */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* 감정 분석 */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="card"
        >
          <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <span className="text-2xl">💭</span>
            감정 분석
          </h3>

          <div className="flex items-center gap-4 mb-6">
            <div
              className="w-20 h-20 rounded-2xl flex items-center justify-center text-4xl"
              style={{ backgroundColor: `${analysis.sentiment.color}20` }}
            >
              {sentimentEmoji}
            </div>
            <div>
              <p
                className="text-2xl font-bold"
                style={{ color: analysis.sentiment.color }}
              >
                {analysis.sentiment.label}
              </p>
              <p className="text-gray-400 text-sm">
                점수: {(analysis.sentiment.score * 100).toFixed(1)}%
              </p>
            </div>
          </div>

          {/* 감정 바 */}
          <div className="mb-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>부정</span>
              <span>중립</span>
              <span>긍정</span>
            </div>
            <div className="h-3 bg-dark-500 rounded-full overflow-hidden">
              <div
                className="h-full transition-all duration-500"
                style={{
                  width: `${((analysis.sentiment.score + 1) / 2) * 100}%`,
                  background: `linear-gradient(to right, #ff6b6b, #95a5a6, #00d26a)`,
                }}
              />
            </div>
          </div>

          {/* 상세 */}
          {analysis.sentiment.details && (
            <div className="grid grid-cols-2 gap-4 pt-4 border-t border-white/5">
              <div>
                <p className="text-xs text-gray-500 mb-1">긍정 표현</p>
                <p className="text-green-400 font-semibold">
                  {analysis.sentiment.details.positive_count}개
                </p>
              </div>
              <div>
                <p className="text-xs text-gray-500 mb-1">부정 표현</p>
                <p className="text-red-400 font-semibold">
                  {analysis.sentiment.details.negative_count}개
                </p>
              </div>
            </div>
          )}
        </motion.div>

        {/* 키워드 */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="card"
        >
          <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <span className="text-2xl">🔑</span>
            핵심 키워드
          </h3>

          <div className="flex flex-wrap gap-2">
            {analysis.keywords.map((kw, index) => (
              <motion.span
                key={kw.keyword}
                initial={{ opacity: 0, scale: 0.8 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.3 + index * 0.05 }}
                className="inline-flex items-center gap-2 px-3 py-2 bg-dark-500 rounded-lg"
                style={{
                  fontSize: `${Math.max(0.75, Math.min(1.25, 0.75 + kw.score))}rem`,
                }}
              >
                <span className="text-white">{kw.keyword}</span>
                <span className="text-xs text-gray-500">
                  {(kw.score * 100).toFixed(0)}%
                </span>
              </motion.span>
            ))}
          </div>

          {analysis.keywords.length === 0 && (
            <p className="text-gray-500 text-sm">추출된 키워드가 없습니다.</p>
          )}
        </motion.div>
      </div>

      {/* 처리 시간 */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.4 }}
        className="text-center text-sm text-gray-500"
      >
        분석 완료 · 처리 시간: {analysis.processing_time_ms}ms
      </motion.div>
    </div>
  )
}

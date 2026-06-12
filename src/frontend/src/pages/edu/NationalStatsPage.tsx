import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { eduApi, getEduToken } from '../../services/eduApi'

type NationalStats = {
  pro_pct: number
  con_pct: number
  stance_changed_pct: number
}

type QuestInfo = {
  quest_id: string
  quest_code: string
  quest_title: string
  pro_line: string
  con_line: string
}

export default function NationalStatsPage() {
  const { questId } = useParams<{ questId: string }>()
  const [stats, setStats] = useState<NationalStats | null>(null)
  const [quest, setQuest] = useState<QuestInfo | null>(null)
  const [studentStance, setStudentStance] = useState<'pro' | 'con' | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    loadStats()
  }, [questId])

  const loadStats = async () => {
    setLoading(true)
    try {
      const data = await eduApi.getNationalStats(questId || '')
      setStats(data.stats)
      setQuest(data.quest)
      setStudentStance(data.student_stance)
    } catch (e) {
      setError(e instanceof Error ? e.message : '통계를 불러올 수 없습니다')
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0D0D0D] text-white">
        불러오는 중...
      </div>
    )
  }

  if (error || !stats || !quest) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-[#0D0D0D] text-white gap-4">
        <p className="text-red-400">{error || '통계를 찾을 수 없습니다'}</p>
        <Link to="/edu" className="text-[#E8521C] underline">
          홈으로
        </Link>
      </div>
    )
  }

  const isMinority = studentStance === 'pro' ? stats.pro_pct < 50 : stats.con_pct < 50
  const studentPct = studentStance === 'pro' ? stats.pro_pct : stats.con_pct

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <header className="border-b border-[#333] px-4 py-3">
        <Link to="/edu" className="text-xs text-[#888] underline">
          ← 홈
        </Link>
      </header>

      <main className="max-w-lg mx-auto px-4 py-8 space-y-8">
        <section>
          <p className="text-xs text-[#888] mb-1">{quest.quest_code}</p>
          <h1 className="text-xl font-bold leading-tight">{quest.quest_title}</h1>
        </section>

        {studentStance && (
          <section className="bg-[#1a1a1a] border border-[#333] rounded-lg p-4">
            <p className="text-sm text-[#888] mb-2">네 입장</p>
            <p className="text-lg font-medium text-[#E8521C]">
              {studentStance === 'pro' ? '찬성' : '반대'} ({studentPct.toFixed(0)}%)
            </p>
            {isMinority && (
              <p className="text-xs text-[#E8521C] mt-2">
                ✦ 너는 소수파에서 출발했어!
              </p>
            )}
          </section>
        )}

        <section className="space-y-4">
          <h2 className="text-sm font-bold text-[#888]">전국 분포</h2>
          
          <div className="relative h-8 bg-[#1a1a1a] rounded-full overflow-hidden">
            <div
              className="absolute top-0 left-0 h-full bg-[#E8521C] transition-all duration-500"
              style={{ width: `${stats.pro_pct}%` }}
            />
            <div
              className="absolute top-0 right-0 h-full bg-[#444] transition-all duration-500"
              style={{ width: `${stats.con_pct}%` }}
            />
          </div>
          
          <div className="flex justify-between text-sm">
            <div>
              <p className="text-[#E8521C] font-bold">{stats.pro_pct.toFixed(0)}%</p>
              <p className="text-xs text-[#888]">찬성</p>
            </div>
            <div className="text-right">
              <p className="text-[#888] font-bold">{stats.con_pct.toFixed(0)}%</p>
              <p className="text-xs text-[#888]">반대</p>
            </div>
          </div>
        </section>

        <section className="bg-[#1a1a1a] border border-[#333] rounded-lg p-4">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-2xl">⚡</span>
            <p className="text-sm text-[#888]">입장을 바꾼 학생</p>
          </div>
          <p className="text-3xl font-bold text-[#E8521C]">
            {stats.stance_changed_pct.toFixed(0)}%
          </p>
          <p className="text-xs text-[#666] mt-2">
            "바꾸는 건 지는 게 아니라 자라는 거야."
          </p>
        </section>

        <section className="border-t border-[#333] pt-6">
          <div className="grid grid-cols-2 gap-3">
            <div className="bg-[#1a1a1a] rounded-lg p-3">
              <p className="text-xs text-[#888]">찬성 요약</p>
              <p className="text-sm mt-1">{quest.pro_line}</p>
            </div>
            <div className="bg-[#1a1a1a] rounded-lg p-3">
              <p className="text-xs text-[#888]">반대 요약</p>
              <p className="text-sm mt-1">{quest.con_line}</p>
            </div>
          </div>
        </section>

        {!getEduToken() && (
          <section className="text-center py-6">
            <Link
              to="/edu"
              className="inline-block px-6 py-3 bg-[#E8521C] text-white rounded-lg font-medium"
            >
              나도 참여하기
            </Link>
          </section>
        )}
      </main>
    </div>
  )
}

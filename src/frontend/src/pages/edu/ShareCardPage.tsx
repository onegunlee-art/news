import { useEffect, useState } from 'react'
import { Link, useParams, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { eduApi, getEduToken } from '../../services/eduApi'

type ShareCard = {
  quest_code: string
  quest_title: string
  initial_stance: 'pro' | 'con'
  final_stance: 'pro' | 'con'
  stance_changed: boolean
  streak_days: number
  tier_name: string
  hero_sentence: string
  national_changed_pct: number | null
}

export default function ShareCardPage() {
  const { sessionId, hash } = useParams<{ sessionId?: string; hash?: string }>()
  const navigate = useNavigate()
  const [card, setCard] = useState<ShareCard | null>(null)
  const [shareUrl, setShareUrl] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [creating, setCreating] = useState(false)

  useEffect(() => {
    if (hash) {
      loadPublicCard()
    } else if (sessionId) {
      loadOrCreateCard()
    }
  }, [hash, sessionId])

  const loadPublicCard = async () => {
    setLoading(true)
    try {
      const data = await eduApi.getShareCardByHash(hash!)
      setCard(data.card)
    } catch (e) {
      setError('카드를 찾을 수 없습니다')
    } finally {
      setLoading(false)
    }
  }

  const loadOrCreateCard = async () => {
    if (!getEduToken()) {
      navigate('/edu')
      return
    }
    setLoading(true)
    try {
      const existing = await eduApi.getShareCard(sessionId!)
      setCard(existing.card)
      setShareUrl(existing.share_url)
    } catch {
      setCreating(true)
      try {
        const created = await eduApi.createShareCard(sessionId!)
        setCard(created.card)
        setShareUrl(created.share_url)
      } catch (e) {
        setError('카드를 생성할 수 없습니다')
      }
      setCreating(false)
    } finally {
      setLoading(false)
    }
  }

  const handleShare = async () => {
    if (!shareUrl) return
    
    if (navigator.share) {
      try {
        await navigator.share({
          title: `GIST EDU - ${card?.quest_title}`,
          text: card?.stance_changed 
            ? `생각이 바뀌었어 ⚡ ${card?.quest_title}`
            : `나의 입장: ${card?.final_stance === 'pro' ? '찬성' : '반대'}`,
          url: shareUrl,
        })
      } catch {
        copyToClipboard()
      }
    } else {
      copyToClipboard()
    }
  }

  const copyToClipboard = () => {
    navigator.clipboard.writeText(shareUrl)
    alert('링크가 복사되었습니다!')
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0D0D0D] text-white">
        {creating ? '카드 생성 중...' : '불러오는 중...'}
      </div>
    )
  }

  if (error || !card) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-[#0D0D0D] text-white gap-4">
        <p className="text-red-400">{error}</p>
        <Link to="/edu" className="text-[#E8521C] underline">
          홈으로
        </Link>
      </div>
    )
  }

  const tierLabel = {
    bronze: 'Bronze Thinker',
    silver: 'Silver Thinker',
    gold: 'Gold Thinker',
  }[card.tier_name] || card.tier_name

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white flex flex-col items-center justify-center p-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ type: 'spring', duration: 0.6 }}
        className="w-full max-w-[320px] bg-[#1a1a1a] rounded-2xl overflow-hidden"
        style={{ aspectRatio: '9/14.5' }}
      >
        <div className="h-full flex flex-col p-6">
          <div className="flex-1 flex flex-col">
            <p className="text-xs text-[#888] mb-1">오늘의 논쟁</p>
            <p className="text-[#E8521C] text-xs font-mono">{card.quest_code}</p>
            
            <h2 className="text-lg font-bold mt-4 leading-tight flex-grow-0">
              "{card.quest_title}"
            </h2>
            
            <div className="mt-6 space-y-3">
              <div className="flex items-center gap-2">
                <span className="text-xs text-[#888]">처음:</span>
                <span className="text-sm">
                  {card.initial_stance === 'pro' ? '찬성' : '반대'}
                </span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-[#888]">지금:</span>
                <span className="text-sm font-bold">
                  {card.final_stance === 'pro' ? '찬성' : '반대'}
                </span>
                {card.stance_changed && (
                  <span className="text-[#E8521C] text-lg">⚡</span>
                )}
              </div>
            </div>
            
            {card.stance_changed && (
              <motion.div
                initial={{ scale: 0.9, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ delay: 0.3 }}
                className="mt-6 bg-[#E8521C]/10 border border-[#E8521C]/30 rounded-lg p-4"
              >
                <p className="text-[#E8521C] font-bold text-2xl text-center font-serif italic">
                  생각이 바뀌었다 ⚡
                </p>
              </motion.div>
            )}
            
            {card.national_changed_pct !== null && (
              <div className="mt-4 text-center">
                <p className="text-xs text-[#888]">
                  전국에서 입장 바꾼 학생
                </p>
                <p className="text-2xl font-bold text-[#E8521C]">
                  {card.national_changed_pct.toFixed(0)}%
                </p>
              </div>
            )}
            
            {card.hero_sentence && (
              <div className="mt-auto pt-4 border-t border-[#333]">
                <p className="text-xs text-[#888] mb-2">나의 핵심 문장</p>
                <p className="text-sm leading-relaxed">
                  "{card.hero_sentence}"
                </p>
              </div>
            )}
          </div>
          
          <div className="mt-4 pt-4 border-t border-[#333] flex items-center justify-between">
            <div>
              <p className="text-xs text-[#E8521C]">{tierLabel}</p>
              {card.streak_days > 0 && (
                <p className="text-xs text-[#888]">{card.streak_days}일 연속</p>
              )}
            </div>
            <div className="text-right">
              <p className="text-xs text-[#888]">GIST EDU</p>
            </div>
          </div>
        </div>
      </motion.div>
      
      {shareUrl && (
        <div className="mt-6 space-y-3 w-full max-w-[320px]">
          <button
            onClick={handleShare}
            className="w-full py-3 bg-[#E8521C] text-white rounded-lg font-medium"
          >
            공유하기
          </button>
          <Link
            to="/edu"
            className="block w-full py-3 text-center border border-[#333] rounded-lg text-[#888]"
          >
            홈으로
          </Link>
        </div>
      )}
      
      {!shareUrl && (
        <div className="mt-6 text-center">
          <p className="text-sm text-[#888] mb-3">
            너도 참여해볼래?
          </p>
          <Link
            to="/edu"
            className="inline-block px-6 py-3 bg-[#E8521C] text-white rounded-lg font-medium"
          >
            GIST EDU 시작하기
          </Link>
        </div>
      )}
    </div>
  )
}

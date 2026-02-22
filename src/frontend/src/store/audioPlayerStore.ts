import { create } from 'zustand'
import { ttsApi } from '../services/api'

interface AudioPlayerState {
  isOpen: boolean
  isPlaying: boolean
  title: string
  fullText: string
  rate: number
  imageUrl: string
  progress: number
  /** 서버 TTS 오디오 URL */
  audioUrl: string | null
  /** 서버 TTS 생성 중 */
  isTtsLoading: boolean
  /** TTS 생성 실패 시 에러 메시지 (사용자 피드백) */
  ttsError: string | null
}

interface AudioPlayerActions {
  /** title, meta(매체설명), narration(본문), critiquePart(The Gist's Critique...) - 구조화 TTS (pause 적용) */
  openAndPlay: (title: string, meta: string, narration: string, critiquePart: string, rate?: number, imageUrl?: string, newsId?: number) => void
  togglePlay: () => void
  pause: () => void
  seek: (progress: number) => void
  skipBack: (seconds?: number) => void
  close: () => void
  setProgress: (progress: number) => void
}

const initial: AudioPlayerState = {
  isOpen: false,
  isPlaying: false,
  title: '',
  fullText: '',
  rate: 1.0,
  imageUrl: '',
  progress: 0,
  audioUrl: null,
  isTtsLoading: false,
  ttsError: null,
}

export const useAudioPlayerStore = create<AudioPlayerState & AudioPlayerActions>((set, _get) => ({
  ...initial,

  setProgress: (progress) => set({ progress }),

  openAndPlay: (title, meta, narration, critiquePart, rate = 1.0, imageUrl = '', newsId?: number) => {
    const t = (title || '').trim()
    const m = (meta || '').trim()
    const n = (narration || '').trim()
    const c = (critiquePart || '').trim()
    const fullText = [t, m, n, c].filter(Boolean).join(' ')
    if (!fullText) return
    // 브라우저 TTS 완전 정지
    if ('speechSynthesis' in window) window.speechSynthesis.cancel()
    set({
      isOpen: true,
      isPlaying: false,
      title: t || 'Listen',
      fullText,
      rate,
      imageUrl,
      progress: 0,
      audioUrl: null,
      isTtsLoading: true,
      ttsError: null,
    })
    // 서버 Google TTS (구조화: 제목 pause 매체설명 pause 내레이션 pause Critique. 캐시 있으면 즉시 반환)
    ttsApi
      .generateStructured(t, m, n, c, newsId)
      .then((res) => {
        const url = res.data?.data?.url
        if (url) {
          set({ audioUrl: url, isTtsLoading: false, ttsError: null })
        } else {
          set({ isTtsLoading: false, ttsError: '오디오 URL을 받지 못했습니다. 다시 시도해 주세요.' })
        }
      })
      .catch((err) => {
        const msg = err.response?.data?.message ?? err.message ?? '오디오 생성에 실패했습니다. 서버 연결을 확인해 주세요.'
        set({ isTtsLoading: false, ttsError: msg })
      })
  },

  togglePlay: () => {
    // 팝업의 handleTogglePlay에서 <audio> 제어
  },

  pause: () => {
    set({ isPlaying: false })
  },

  seek: (_progress) => {
    // 팝업의 handleSeek에서 <audio> 제어
  },

  skipBack: (_seconds) => {
    // 팝업의 handleSkipBack에서 <audio> 제어
  },

  close: () => {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel()
    set({ ...initial })
  },
}))

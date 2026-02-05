import { create } from 'zustand'

/** 한글 TTS 대략 초당 글자 수 (rate 1.0 기준) */
const CHARS_PER_SEC = 12

interface AudioPlayerState {
  isOpen: boolean
  isPlaying: boolean
  title: string
  fullText: string
  rate: number
  /** 썸네일 이미지 URL */
  imageUrl: string
  /** 재생 진행 0~1 */
  progress: number
  /** 현재 재생이 시작된 위치(문자 인덱스) */
  startCharIndex: number
  /** 현재 구간 재생 시작 시각 (ms) */
  startTime: number
  /** 진행률 갱신용 타이머 ID */
  progressTimerId: ReturnType<typeof setInterval> | null
  /** 재생 중인 utterance (취소용) */
  _utterance: SpeechSynthesisUtterance | null
}

interface AudioPlayerActions {
  /** 팝업 열고 텍스트 재생 시작 */
  openAndPlay: (title: string, text: string, rate?: number, imageUrl?: string) => void
  /** 재생 / 일시정지 토글 */
  togglePlay: () => void
  /** 일시정지(취소) */
  pause: () => void
  /** 진행 위치 이동 (0~1), 해당 위치부터 재생 */
  seek: (progress: number) => void
  /** 15초 뒤로 이동 */
  skipBack: (seconds?: number) => void
  /** 팝업 닫기 및 재생 중지 */
  close: () => void
  /** 진행률만 갱신 (내부용) */
  setProgress: (progress: number) => void
  /** 타이머 설정 (내부용) */
  setProgressTimerId: (id: ReturnType<typeof setInterval> | null) => void
}

const initial: AudioPlayerState = {
  isOpen: false,
  isPlaying: false,
  title: '',
  fullText: '',
  rate: 1.0,
  imageUrl: '',
  progress: 0,
  startCharIndex: 0,
  startTime: 0,
  progressTimerId: null,
  _utterance: null,
}

function clearProgressTimer(get: () => AudioPlayerState & AudioPlayerActions) {
  const id = get().progressTimerId
  if (id != null) {
    clearInterval(id)
    get().setProgressTimerId(null)
  }
}

function startProgressTimer(get: () => AudioPlayerState & AudioPlayerActions) {
  clearProgressTimer(get)
  const id = setInterval(() => {
    const state = get()
    if (!state.isPlaying || !state.fullText.length) return
    const elapsedSec = (Date.now() - state.startTime) / 1000
    const charsPerSec = CHARS_PER_SEC * state.rate
    const currentCharIndex = state.startCharIndex + elapsedSec * charsPerSec
    const progress = Math.min(1, currentCharIndex / state.fullText.length)
    get().setProgress(progress)
    if (progress >= 1) clearProgressTimer(get)
  }, 200)
  get().setProgressTimerId(id)
}

export const useAudioPlayerStore = create<AudioPlayerState & AudioPlayerActions>((set, get) => ({
  ...initial,

  setProgress: (progress) => set({ progress }),
  setProgressTimerId: (progressTimerId) => set({ progressTimerId }),

  openAndPlay: (title, text, rate = 1.0, imageUrl = '') => {
    const fullText = (text || '').trim()
    if (!fullText || !('speechSynthesis' in window)) return
    window.speechSynthesis.cancel()
    clearProgressTimer(get)
    set({
      isOpen: true,
      isPlaying: false,
      title,
      fullText,
      rate,
      imageUrl,
      progress: 0,
      startCharIndex: 0,
      startTime: 0,
      progressTimerId: null,
      _utterance: null,
    })
    const utterance = new SpeechSynthesisUtterance(fullText)
    utterance.lang = 'ko-KR'
    utterance.rate = rate
    utterance.pitch = 1.0
    const voices = window.speechSynthesis.getVoices()
    const ko = voices.find((v) => v.lang.includes('ko'))
    if (ko) utterance.voice = ko
    utterance.onstart = () => {
      set({ isPlaying: true, startTime: Date.now() })
      startProgressTimer(get)
    }
    utterance.onend = () => {
      clearProgressTimer(get)
      set({ isPlaying: false, progress: 1, startCharIndex: get().fullText.length, _utterance: null })
    }
    utterance.onerror = () => {
      clearProgressTimer(get)
      set({ isPlaying: false, _utterance: null })
    }
    set({ _utterance: utterance })
    window.speechSynthesis.speak(utterance)
  },

  togglePlay: () => {
    const state = get()
    if (!state.isOpen || !state.fullText) return
    if (state.isPlaying) {
      window.speechSynthesis.cancel()
      clearProgressTimer(get)
      const elapsedSec = (Date.now() - state.startTime) / 1000
      const charsPerSec = CHARS_PER_SEC * state.rate
      const newStartIndex = Math.min(
        state.fullText.length,
        state.startCharIndex + Math.floor(elapsedSec * charsPerSec)
      )
      set({ isPlaying: false, startCharIndex: newStartIndex, progress: newStartIndex / state.fullText.length, _utterance: null })
    } else {
      const from = state.startCharIndex
      if (from >= state.fullText.length) return
      const sub = state.fullText.substring(from)
      const utterance = new SpeechSynthesisUtterance(sub)
      utterance.lang = 'ko-KR'
      utterance.rate = state.rate
      utterance.pitch = 1.0
      const voices = window.speechSynthesis.getVoices()
      const ko = voices.find((v) => v.lang.includes('ko'))
      if (ko) utterance.voice = ko
      utterance.onstart = () => {
        set({ isPlaying: true, startTime: Date.now() })
        startProgressTimer(get)
      }
      utterance.onend = () => {
        clearProgressTimer(get)
        set({ isPlaying: false, progress: 1, startCharIndex: state.fullText.length, _utterance: null })
      }
      utterance.onerror = () => {
        clearProgressTimer(get)
        set({ isPlaying: false, _utterance: null })
      }
      set({ _utterance: utterance })
      window.speechSynthesis.speak(utterance)
    }
  },

  pause: () => {
    window.speechSynthesis.cancel()
    clearProgressTimer(get)
    const state = get()
    if (state.isPlaying && state.fullText) {
      const elapsedSec = (Date.now() - state.startTime) / 1000
      const charsPerSec = CHARS_PER_SEC * state.rate
      const newStartIndex = Math.min(
        state.fullText.length,
        state.startCharIndex + Math.floor(elapsedSec * charsPerSec)
      )
      set({ isPlaying: false, startCharIndex: newStartIndex, progress: newStartIndex / state.fullText.length })
    } else {
      set({ isPlaying: false })
    }
    set({ _utterance: null })
  },

  seek: (progress) => {
    const state = get()
    if (!state.fullText) return
    window.speechSynthesis.cancel()
    clearProgressTimer(get)
    const p = Math.max(0, Math.min(1, progress))
    const startCharIndex = Math.floor(p * state.fullText.length)
    const sub = state.fullText.substring(startCharIndex)
    if (!sub) {
      set({ progress: 1, startCharIndex: state.fullText.length, isPlaying: false, _utterance: null })
      return
    }
    set({ progress: p, startCharIndex, isPlaying: false })
    const utterance = new SpeechSynthesisUtterance(sub)
    utterance.lang = 'ko-KR'
    utterance.rate = state.rate
    utterance.pitch = 1.0
    const voices = window.speechSynthesis.getVoices()
    const ko = voices.find((v) => v.lang.includes('ko'))
    if (ko) utterance.voice = ko
    utterance.onstart = () => {
      set({ isPlaying: true, startTime: Date.now() })
      startProgressTimer(get)
    }
    utterance.onend = () => {
      clearProgressTimer(get)
      set({ isPlaying: false, progress: 1, startCharIndex: state.fullText.length, _utterance: null })
    }
    utterance.onerror = () => {
      clearProgressTimer(get)
      set({ isPlaying: false, _utterance: null })
    }
    set({ _utterance: utterance })
    window.speechSynthesis.speak(utterance)
  },

  skipBack: (seconds = 15) => {
    const state = get()
    if (!state.fullText) return
    // 15초에 해당하는 글자 수 계산
    const charsToSkip = seconds * CHARS_PER_SEC * state.rate
    const currentCharIndex = Math.floor(state.progress * state.fullText.length)
    const newCharIndex = Math.max(0, currentCharIndex - charsToSkip)
    const newProgress = newCharIndex / state.fullText.length
    // seek 함수로 이동
    get().seek(newProgress)
  },

  close: () => {
    window.speechSynthesis.cancel()
    clearProgressTimer(get)
    set({ ...initial })
  },
}))

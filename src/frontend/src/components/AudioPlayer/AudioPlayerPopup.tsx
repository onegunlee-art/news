import { useRef, useEffect, useState, useCallback } from 'react'
import { useAudioPlayerStore } from '../../store/audioPlayerStore'

function formatTime(seconds: number): string {
  if (!isFinite(seconds) || seconds < 0) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = Math.floor(seconds % 60)
  return `${m}:${s.toString().padStart(2, '0')}`
}

/**
 * Media Session API 메타데이터 설정
 * - 모바일 잠금 화면, 알림 바, 백그라운드 재생 컨트롤에 표시됨
 */
function updateMediaSession(title: string, imageUrl: string) {
  if (!('mediaSession' in navigator)) return
  const artwork: MediaImage[] = imageUrl
    ? [
        { src: imageUrl, sizes: '96x96', type: 'image/jpeg' },
        { src: imageUrl, sizes: '256x256', type: 'image/jpeg' },
        { src: imageUrl, sizes: '512x512', type: 'image/jpeg' },
      ]
    : []
  navigator.mediaSession.metadata = new MediaMetadata({
    title: title || '뉴스 오디오',
    artist: 'The Gist',
    album: 'News Audio',
    artwork,
  })
}

export default function AudioPlayerPopup() {
  const isOpen = useAudioPlayerStore((s) => s.isOpen)
  const isPlaying = useAudioPlayerStore((s) => s.isPlaying)
  const title = useAudioPlayerStore((s) => s.title)
  const imageUrl = useAudioPlayerStore((s) => s.imageUrl)
  const progress = useAudioPlayerStore((s) => s.progress)
  const audioUrl = useAudioPlayerStore((s) => s.audioUrl)
  const isTtsLoading = useAudioPlayerStore((s) => s.isTtsLoading)
  const ttsError = useAudioPlayerStore((s) => s.ttsError)
  const close = useAudioPlayerStore((s) => s.close)
  const setProgress = useAudioPlayerStore((s) => s.setProgress)

  const audioRef = useRef<HTMLAudioElement | null>(null)
  const [duration, setDuration] = useState(0)
  // 사용자가 드래그 중인지 (timeupdate로 progress 덮어쓰지 않기 위해)
  const isSeeking = useRef(false)

  const currentSec = duration > 0 ? progress * duration : 0

  // ── 오디오 엘리먼트를 컴포넌트 마운트 시 한 번만 생성 (DOM 밖) ──
  // DOM에 넣지 않고 JS에서만 관리하면 React re-render에 의해 파괴되지 않음
  useEffect(() => {
    if (!audioRef.current) {
      const el = new Audio()
      el.preload = 'auto'
      // iOS Safari: 백그라운드 재생을 위해 필요
      el.setAttribute('playsinline', '')
      el.setAttribute('webkit-playsinline', '')
      audioRef.current = el
    }
    return () => {
      // 컴포넌트 최종 언마운트 시에만 정리
      const el = audioRef.current
      if (el) {
        el.pause()
        el.src = ''
        el.removeAttribute('src')
        audioRef.current = null
      }
    }
  }, [])

  // ── 오디오 이벤트 리스너 ──
  useEffect(() => {
    const el = audioRef.current
    if (!el) return

    const updateDuration = () => {
      if (el.duration && isFinite(el.duration) && el.duration > 0) {
        setDuration(el.duration)
      }
    }

    const onTimeUpdate = () => {
      if (isSeeking.current) return
      if (el.duration && isFinite(el.duration) && el.duration > 0) {
        setProgress(el.currentTime / el.duration)
        // duration이 아직 안 잡혔으면 여기서도 갱신
        if (duration === 0) updateDuration()
      }
    }

    const onEnded = () => {
      setProgress(1)
      useAudioPlayerStore.setState({ isPlaying: false })
      if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = 'paused'
      }
    }

    const onPlay = () => {
      useAudioPlayerStore.setState({ isPlaying: true })
      if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = 'playing'
      }
    }

    const onPause = () => {
      useAudioPlayerStore.setState({ isPlaying: false })
      if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = 'paused'
      }
    }

    // 여러 이벤트에서 duration 감지 (브라우저 호환성)
    el.addEventListener('loadedmetadata', updateDuration)
    el.addEventListener('durationchange', updateDuration)
    el.addEventListener('canplay', updateDuration)
    el.addEventListener('loadeddata', updateDuration)
    el.addEventListener('timeupdate', onTimeUpdate)
    el.addEventListener('ended', onEnded)
    el.addEventListener('play', onPlay)
    el.addEventListener('pause', onPause)

    return () => {
      el.removeEventListener('loadedmetadata', updateDuration)
      el.removeEventListener('durationchange', updateDuration)
      el.removeEventListener('canplay', updateDuration)
      el.removeEventListener('loadeddata', updateDuration)
      el.removeEventListener('timeupdate', onTimeUpdate)
      el.removeEventListener('ended', onEnded)
      el.removeEventListener('play', onPlay)
      el.removeEventListener('pause', onPause)
    }
  // duration을 deps에 포함하면 무한 루프 → 의도적으로 제외
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [setProgress])

  // ── audioUrl 변경 시 소스 로드 & 자동 재생 ──
  useEffect(() => {
    const el = audioRef.current
    if (!el || !audioUrl) return

    setDuration(0)
    el.src = audioUrl
    el.load()

    // 자동 재생 (모바일에서 실패할 수 있으므로 catch)
    const playPromise = el.play()
    if (playPromise) {
      playPromise.catch(() => {
        // autoplay 정책으로 차단된 경우 - 사용자가 수동으로 play 버튼 누르면 됨
      })
    }

    // Media Session 메타데이터 설정
    updateMediaSession(title, imageUrl)
  }, [audioUrl, title, imageUrl])

  // ── Media Session 액션 핸들러 등록 ──
  useEffect(() => {
    if (!('mediaSession' in navigator)) return

    const el = audioRef.current

    const actionHandlers: [MediaSessionAction, MediaSessionActionHandler][] = [
      ['play', () => { el?.play().catch(() => {}) }],
      ['pause', () => { el?.pause() }],
      ['stop', () => {
        if (el) { el.pause(); el.currentTime = 0 }
        close()
      }],
      ['seekbackward', (details) => {
        if (!el) return
        const skipTime = details?.seekOffset || 15
        el.currentTime = Math.max(0, el.currentTime - skipTime)
      }],
      ['seekforward', (details) => {
        if (!el) return
        const skipTime = details?.seekOffset || 15
        el.currentTime = Math.min(el.duration || 0, el.currentTime + skipTime)
      }],
      ['seekto', (details) => {
        if (!el || details?.seekTime == null) return
        el.currentTime = details.seekTime
      }],
    ]

    for (const [action, handler] of actionHandlers) {
      try {
        navigator.mediaSession.setActionHandler(action, handler)
      } catch {
        // 지원하지 않는 액션 무시
      }
    }

    // Position State 업데이트 (잠금 화면 프로그레스 바)
    const updatePositionInterval = setInterval(() => {
      if (!el || !isFinite(el.duration) || el.duration <= 0) return
      try {
        navigator.mediaSession.setPositionState({
          duration: el.duration,
          playbackRate: el.playbackRate,
          position: Math.min(el.currentTime, el.duration),
        })
      } catch {
        // 무시
      }
    }, 1000)

    return () => {
      clearInterval(updatePositionInterval)
      for (const [action] of actionHandlers) {
        try {
          navigator.mediaSession.setActionHandler(action, null)
        } catch {
          // 무시
        }
      }
    }
  }, [close])

  // ── 닫힐 때 오디오 정지 ──
  useEffect(() => {
    if (!isOpen) {
      const el = audioRef.current
      if (el) {
        el.pause()
        el.src = ''
        el.removeAttribute('src')
      }
      setDuration(0)
    }
  }, [isOpen])

  // ── 재생/일시정지 토글 ──
  const handleTogglePlay = useCallback(() => {
    const el = audioRef.current
    if (!el || !audioUrl) return
    if (isPlaying) {
      el.pause()
    } else {
      el.play().catch(() => {})
    }
  }, [isPlaying, audioUrl])

  // ── 프로그레스 바 seek (드래그 시작) ──
  const handleSeekStart = useCallback(() => {
    isSeeking.current = true
  }, [])

  // ── 프로그레스 바 seek (값 변경) ──
  const handleSeekChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const value = Number(e.target.value) / 100
    setProgress(value)
  }, [setProgress])

  // ── 프로그레스 바 seek (드래그 끝 → 실제 이동) ──
  const handleSeekEnd = useCallback((e: React.MouseEvent<HTMLInputElement> | React.TouchEvent<HTMLInputElement>) => {
    isSeeking.current = false
    const el = audioRef.current
    const target = e.target as HTMLInputElement
    const value = Number(target.value) / 100
    if (el && el.duration && isFinite(el.duration)) {
      el.currentTime = value * el.duration
      setProgress(value)
    }
  }, [setProgress])

  // ── 15초 뒤로 ──
  const handleSkipBack = useCallback((seconds = 15) => {
    const el = audioRef.current
    if (!el) return
    // duration 없어도 currentTime 기준으로 동작
    el.currentTime = Math.max(0, el.currentTime - seconds)
    if (el.duration && isFinite(el.duration)) {
      setProgress(el.currentTime / el.duration)
    }
  }, [setProgress])

  // ── 15초 앞으로 ──
  const handleSkipForward = useCallback((seconds = 15) => {
    const el = audioRef.current
    if (!el) return
    const maxTime = (el.duration && isFinite(el.duration)) ? el.duration : el.currentTime + seconds
    el.currentTime = Math.min(maxTime, el.currentTime + seconds)
    if (el.duration && isFinite(el.duration)) {
      setProgress(el.currentTime / el.duration)
    }
  }, [setProgress])

  // ── 닫기 ──
  const handleClose = useCallback(() => {
    const el = audioRef.current
    if (el) {
      el.pause()
      el.src = ''
      el.removeAttribute('src')
    }
    if ('mediaSession' in navigator) {
      navigator.mediaSession.playbackState = 'none'
    }
    close()
  }, [close])

  if (!isOpen) return null

  const hasAudio = !!audioUrl
  const canControl = hasAudio

  return (
    <div
      className="fixed bottom-0 left-0 right-0 z-[100] bg-gray-100 border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]"
      role="dialog"
      aria-label="오디오 재생"
    >
      <div className="max-w-7xl mx-auto px-4 py-3">
        {/* 로딩 상태 */}
        {isTtsLoading && (
          <div className="flex items-center gap-2 mb-2">
            <div className="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            <p className="text-sm text-gray-500">Google Voice로 오디오 생성 중...</p>
          </div>
        )}

        {/* TTS 실패 시 안내 (구체적 에러 메시지 표시) */}
        {!isTtsLoading && !audioUrl && (
          <p className="text-sm text-red-500 mb-2">{ttsError || '오디오 생성에 실패했습니다. 잠시 후 다시 시도해 주세요.'}</p>
        )}

        {/* 재생 시간 + 진행 바 */}
        <div className="flex items-center gap-2 mb-2">
          <span className="text-xs text-gray-500 tabular-nums w-10">{formatTime(currentSec)}</span>
          <input
            type="range"
            min={0}
            max={100}
            step={0.1}
            value={Math.round(progress * 1000) / 10}
            onMouseDown={handleSeekStart}
            onTouchStart={handleSeekStart}
            onChange={handleSeekChange}
            onMouseUp={handleSeekEnd}
            onTouchEnd={handleSeekEnd}
            disabled={!canControl}
            className="flex-1 h-1.5 rounded-full appearance-none bg-gray-200 accent-primary-500 cursor-pointer disabled:opacity-50"
            aria-label="재생 위치"
          />
          <span className="text-xs text-gray-500 tabular-nums w-10 text-right">{formatTime(duration)}</span>
        </div>

        <div className="flex items-center justify-between">
          {/* 왼쪽: 썸네일 + 제목 */}
          <div className="flex items-center gap-3 flex-1 min-w-0">
            {imageUrl && (
              <img
                src={imageUrl}
                alt=""
                className="w-12 h-12 object-cover rounded flex-shrink-0"
                onError={(e) => {
                  (e.target as HTMLImageElement).style.display = 'none'
                }}
              />
            )}
            <div className="min-w-0">
              <h3 className="text-sm font-medium text-gray-900 truncate" title={title}>
                {title || '재생 중'}
              </h3>
              {audioUrl && (
                <p className="text-xs text-green-600">Google Voice</p>
              )}
            </div>
          </div>

          {/* 오른쪽: 컨트롤 버튼들 */}
          <div className="flex items-center gap-1 flex-shrink-0">
            {/* 15초 뒤로 */}
            <button
              type="button"
              onClick={() => handleSkipBack(15)}
              disabled={!canControl}
              className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-40"
              aria-label="15초 뒤로"
            >
              <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M11.99 5V1l-5 5 5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6h-2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z" />
                <text x="12" y="15.5" textAnchor="middle" fontSize="7" fontWeight="bold" fill="currentColor">15</text>
              </svg>
            </button>

            {/* 재생 / 일시정지 */}
            <button
              type="button"
              onClick={handleTogglePlay}
              disabled={!canControl}
              className="w-12 h-12 flex items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700 transition-colors disabled:opacity-40"
              aria-label={isPlaying ? '일시정지' : '재생'}
            >
              {isPlaying ? (
                <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                </svg>
              ) : (
                <svg className="w-6 h-6 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7L8 5z" />
                </svg>
              )}
            </button>

            {/* 15초 앞으로 */}
            <button
              type="button"
              onClick={() => handleSkipForward(15)}
              disabled={!canControl}
              className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-40"
              aria-label="15초 앞으로"
            >
              <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12.01 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8z" />
                <text x="12" y="15.5" textAnchor="middle" fontSize="7" fontWeight="bold" fill="currentColor">15</text>
              </svg>
            </button>

            {/* 닫기 */}
            <button
              type="button"
              onClick={handleClose}
              className="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors ml-1"
              aria-label="닫기"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

import { useRef, useEffect, useState, useCallback } from 'react'
import { useAudioPlayerStore } from '../../store/audioPlayerStore'

function formatTime(seconds: number): string {
  const m = Math.floor(seconds / 60)
  const s = Math.floor(seconds % 60)
  return `${m}:${s.toString().padStart(2, '0')}`
}

export default function AudioPlayerPopup() {
  const isOpen = useAudioPlayerStore((s) => s.isOpen)
  const isPlaying = useAudioPlayerStore((s) => s.isPlaying)
  const title = useAudioPlayerStore((s) => s.title)
  const imageUrl = useAudioPlayerStore((s) => s.imageUrl)
  const progress = useAudioPlayerStore((s) => s.progress)
  const audioUrl = useAudioPlayerStore((s) => s.audioUrl)
  const isTtsLoading = useAudioPlayerStore((s) => s.isTtsLoading)
  const close = useAudioPlayerStore((s) => s.close)
  const setProgress = useAudioPlayerStore((s) => s.setProgress)

  const audioRef = useRef<HTMLAudioElement>(null)
  const [duration, setDuration] = useState(0)

  const currentSec = duration > 0 ? progress * duration : 0
  const totalSec = duration

  // 오디오 이벤트 리스너
  useEffect(() => {
    const el = audioRef.current
    if (!el) return

    const onLoadedMetadata = () => {
      if (el.duration && isFinite(el.duration)) setDuration(el.duration)
    }
    const onTimeUpdate = () => {
      if (el.duration && isFinite(el.duration)) {
        setProgress(el.currentTime / el.duration)
      }
    }
    const onEnded = () => {
      setProgress(1)
      useAudioPlayerStore.setState({ isPlaying: false })
    }
    const onPlay = () => useAudioPlayerStore.setState({ isPlaying: true })
    const onPause = () => useAudioPlayerStore.setState({ isPlaying: false })

    el.addEventListener('loadedmetadata', onLoadedMetadata)
    el.addEventListener('timeupdate', onTimeUpdate)
    el.addEventListener('ended', onEnded)
    el.addEventListener('play', onPlay)
    el.addEventListener('pause', onPause)

    return () => {
      el.removeEventListener('loadedmetadata', onLoadedMetadata)
      el.removeEventListener('timeupdate', onTimeUpdate)
      el.removeEventListener('ended', onEnded)
      el.removeEventListener('play', onPlay)
      el.removeEventListener('pause', onPause)
    }
  }, [setProgress])

  // audioUrl이 설정되면 자동 재생
  useEffect(() => {
    if (audioUrl && audioRef.current) {
      audioRef.current.src = audioUrl
      audioRef.current.load()
      audioRef.current.play().catch(() => {})
    }
  }, [audioUrl])

  // 닫을 때 오디오 정지
  useEffect(() => {
    if (!isOpen && audioRef.current) {
      audioRef.current.pause()
      audioRef.current.src = ''
    }
  }, [isOpen])

  const handleTogglePlay = useCallback(() => {
    const el = audioRef.current
    if (!el || !audioUrl) return
    if (isPlaying) {
      el.pause()
    } else {
      el.play().catch(() => {})
    }
  }, [isPlaying, audioUrl])

  const handleSeek = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const el = audioRef.current
    if (!el || !duration) return
    const value = Number(e.target.value)
    el.currentTime = (value / 100) * duration
    setProgress(value / 100)
  }, [duration, setProgress])

  const handleSkipBack = useCallback((seconds = 15) => {
    const el = audioRef.current
    if (!el || !duration) return
    el.currentTime = Math.max(0, el.currentTime - seconds)
    setProgress(el.currentTime / duration)
  }, [duration, setProgress])

  const handleClose = useCallback(() => {
    const el = audioRef.current
    if (el) {
      el.pause()
      el.src = ''
    }
    close()
  }, [close])

  if (!isOpen) return null

  return (
    <div
      className="fixed bottom-0 left-0 right-0 z-[100] bg-gray-100 border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]"
      role="dialog"
      aria-label="오디오 재생"
    >
      {/* 항상 존재하는 오디오 엘리먼트 */}
      <audio ref={audioRef} preload="metadata" />

      <div className="max-w-7xl mx-auto px-4 py-3">
        {/* 로딩 상태 */}
        {isTtsLoading && (
          <div className="flex items-center gap-2 mb-2">
            <div className="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
            <p className="text-sm text-gray-500">Google Voice로 오디오 생성 중...</p>
          </div>
        )}

        {/* TTS 실패 시 안내 */}
        {!isTtsLoading && !audioUrl && (
          <p className="text-sm text-red-500 mb-2">오디오 생성에 실패했습니다. 잠시 후 다시 시도해주세요.</p>
        )}

        {/* 재생 시간 + 진행 바 */}
        <div className="flex items-center gap-2 mb-2">
          <span className="text-xs text-gray-500 tabular-nums w-9">{formatTime(currentSec)}</span>
          <input
            type="range"
            min={0}
            max={100}
            value={Math.round(progress * 100)}
            onChange={handleSeek}
            disabled={!audioUrl}
            className="flex-1 h-1.5 rounded-full appearance-none bg-gray-200 accent-primary-500 cursor-pointer disabled:opacity-50"
            aria-label="재생 위치"
          />
          <span className="text-xs text-gray-500 tabular-nums w-9 text-right">{formatTime(totalSec)}</span>
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
          <div className="flex items-center gap-2 flex-shrink-0">
            <button
              type="button"
              onClick={() => handleSkipBack(15)}
              disabled={!audioUrl}
              className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-40"
              aria-label="15초 뒤로"
            >
              <svg className="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.67 5.5L2 12l5.67 6.5V14c3.31 0 6 2.69 6 6h2c0-4.42-3.58-8-8-8V5.5z" />
              </svg>
            </button>
            <button
              type="button"
              onClick={handleTogglePlay}
              disabled={!audioUrl}
              className="w-10 h-10 flex items-center justify-center text-gray-700 hover:text-gray-900 transition-colors disabled:opacity-40"
              aria-label={isPlaying ? '일시정지' : '재생'}
            >
              {isPlaying ? (
                <svg className="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                </svg>
              ) : (
                <svg className="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7L8 5z" />
                </svg>
              )}
            </button>
            <button
              type="button"
              onClick={handleClose}
              className="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors"
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

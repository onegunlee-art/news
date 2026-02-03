import { useAudioPlayerStore } from '../../store/audioPlayerStore'

export default function AudioPlayerPopup() {
  const isOpen = useAudioPlayerStore((s) => s.isOpen)
  const isPlaying = useAudioPlayerStore((s) => s.isPlaying)
  const title = useAudioPlayerStore((s) => s.title)
  const progress = useAudioPlayerStore((s) => s.progress)
  const togglePlay = useAudioPlayerStore((s) => s.togglePlay)
  const seek = useAudioPlayerStore((s) => s.seek)
  const close = useAudioPlayerStore((s) => s.close)

  if (!isOpen) return null

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = Number(e.target.value) / 100
    seek(value)
  }

  return (
    <div
      className="fixed bottom-20 left-4 right-4 md:left-auto md:right-6 md:bottom-6 md:w-[380px] z-50 rounded-2xl shadow-xl bg-white border border-gray-200 overflow-hidden"
      role="dialog"
      aria-label="오디오 재생"
    >
      <div className="p-4">
        <div className="flex items-center justify-between gap-2 mb-3">
          <h3 className="text-sm font-semibold text-gray-900 truncate flex-1 min-w-0" title={title}>
            {title || '재생 중'}
          </h3>
          <button
            type="button"
            onClick={close}
            className="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
            aria-label="닫기"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* 재생 위치 조절 바 */}
        <div className="flex items-center gap-2 mb-3">
          <input
            type="range"
            min={0}
            max={100}
            value={Math.round(progress * 100)}
            onChange={handleSeek}
            className="flex-1 h-2 rounded-full appearance-none bg-gray-200 accent-primary-500 cursor-pointer"
            aria-label="재생 위치"
          />
          <span className="text-xs text-gray-500 w-8 text-right">
            {Math.round(progress * 100)}%
          </span>
        </div>

        <div className="flex items-center justify-center">
          <button
            type="button"
            onClick={togglePlay}
            className="w-12 h-12 rounded-full bg-primary-500 text-white flex items-center justify-center hover:bg-primary-600 transition-colors shadow"
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
        </div>
      </div>
    </div>
  )
}

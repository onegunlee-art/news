import { useAudioPlayerStore } from '../../store/audioPlayerStore'

/** TTS 예상: 초당 글자 수 (rate 1.0 기준) - store와 동일 */
const CHARS_PER_SEC = 12

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
  const fullText = useAudioPlayerStore((s) => s.fullText)
  const rate = useAudioPlayerStore((s) => s.rate)
  const togglePlay = useAudioPlayerStore((s) => s.togglePlay)
  const skipBack = useAudioPlayerStore((s) => s.skipBack)
  const seek = useAudioPlayerStore((s) => s.seek)
  const close = useAudioPlayerStore((s) => s.close)

  const totalSec = fullText.length > 0 ? fullText.length / (CHARS_PER_SEC * rate) : 0
  const currentSec = progress * totalSec

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = Number(e.target.value)
    seek(value / 100)
  }

  if (!isOpen) return null

  return (
    <div
      className="fixed bottom-0 left-0 right-0 z-[100] bg-gray-100 border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]"
      role="dialog"
      aria-label="오디오 재생"
    >
      <div className="max-w-7xl mx-auto px-4 py-3">
        {/* 재생 시간 + 진행 바 */}
        <div className="flex items-center gap-2 mb-2">
          <span className="text-xs text-gray-500 tabular-nums w-9">{formatTime(currentSec)}</span>
          <input
            type="range"
            min={0}
            max={100}
            value={Math.round(progress * 100)}
            onChange={handleSeek}
            className="flex-1 h-1.5 rounded-full appearance-none bg-gray-200 accent-primary-500 cursor-pointer"
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
            <h3 className="text-sm font-medium text-gray-900 truncate" title={title}>
              {title || '재생 중'}
            </h3>
          </div>

          {/* 오른쪽: 컨트롤 버튼들 */}
          <div className="flex items-center gap-2 flex-shrink-0">
            <button
              type="button"
              onClick={() => skipBack(15)}
              className="w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors"
              aria-label="15초 뒤로"
            >
              <svg className="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.67 5.5L2 12l5.67 6.5V14c3.31 0 6 2.69 6 6h2c0-4.42-3.58-8-8-8V5.5z" />
              </svg>
            </button>
            <button
              type="button"
              onClick={togglePlay}
              className="w-10 h-10 flex items-center justify-center text-gray-700 hover:text-gray-900 transition-colors"
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
              onClick={close}
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

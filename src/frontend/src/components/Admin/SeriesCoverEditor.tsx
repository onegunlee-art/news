import { useState, useEffect, useMemo, useCallback, useRef } from 'react'
import { seriesCoversApi, type SeriesListItem } from '../../services/api'
import MaterialIcon from '../Common/MaterialIcon'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'

interface SeriesCoverEditorProps {
  onMessage?: (type: 'success' | 'error', text: string) => void
}

export default function SeriesCoverEditor({ onMessage }: SeriesCoverEditorProps) {
  const [seriesList, setSeriesList] = useState<SeriesListItem[]>([])
  const [selectedSeriesId, setSelectedSeriesId] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  // 편집 상태
  const [coverText, setCoverText] = useState('')
  const [textColor, setTextColor] = useState('#ffffff')
  const [textSize, setTextSize] = useState(24)
  const [textX, setTextX] = useState(50)
  const [textY, setTextY] = useState(50)
  const [isFeatured, setIsFeatured] = useState(false)
  const [displayOrder, setDisplayOrder] = useState(0)

  // 드래그 상태
  const [isDragging, setIsDragging] = useState(false)
  const previewRef = useRef<HTMLDivElement>(null)

  // 시리즈 목록 로드
  useEffect(() => {
    setLoading(true)
    seriesCoversApi.getSeriesList()
      .then((res) => {
        if (res.data?.success) {
          setSeriesList(res.data.data?.series ?? [])
        }
      })
      .catch(() => {
        onMessage?.('error', '시리즈 목록을 불러오지 못했습니다.')
      })
      .finally(() => setLoading(false))
  }, [onMessage])

  // 선택된 시리즈 정보
  const selectedSeries = useMemo(() => {
    return seriesList.find((s) => s.series_id === selectedSeriesId)
  }, [seriesList, selectedSeriesId])

  // 선택된 시리즈 변경 시 편집 상태 초기화
  useEffect(() => {
    if (!selectedSeries) {
      setCoverText('')
      setTextColor('#ffffff')
      setTextSize(24)
      setTextX(50)
      setTextY(50)
      setIsFeatured(false)
      setDisplayOrder(0)
      return
    }
    
    setCoverText(selectedSeries.cover_text ?? '')
    setTextColor(selectedSeries.text_color ?? '#ffffff')
    setTextSize(selectedSeries.text_size ?? 24)
    setTextX(selectedSeries.text_x ?? 50)
    setTextY(selectedSeries.text_y ?? 50)
    setIsFeatured(!!selectedSeries.is_featured)
    setDisplayOrder(selectedSeries.display_order ?? 0)
  }, [selectedSeries])

  const imageUrl = useMemo(() => {
    if (!selectedSeries) return ''
    if (selectedSeries.first_article_image) return selectedSeries.first_article_image
    return getPlaceholderImageUrl(
      {
        id: selectedSeries.first_article_id ?? undefined,
        title: selectedSeries.series_title ?? 'Series',
        description: null,
        published_at: null,
        category: null,
      },
      400,
      500
    )
  }, [selectedSeries])

  const handleSave = async () => {
    if (!selectedSeriesId) return
    setSaving(true)
    try {
      await seriesCoversApi.save({
        series_id: selectedSeriesId,
        cover_text: coverText || null,
        text_color: textColor,
        text_size: textSize,
        text_x: textX,
        text_y: textY,
        is_featured: isFeatured,
        display_order: displayOrder,
      })
      
      // 목록 갱신
      const res = await seriesCoversApi.getSeriesList()
      if (res.data?.success) {
        setSeriesList(res.data.data?.series ?? [])
      }
      
      onMessage?.('success', '표지 설정이 저장되었습니다.')
    } catch {
      onMessage?.('error', '저장에 실패했습니다.')
    } finally {
      setSaving(false)
    }
  }

  // 드래그로 텍스트 위치 조정
  const handleMouseDown = useCallback((e: React.MouseEvent) => {
    if (!previewRef.current) return
    setIsDragging(true)
    e.preventDefault()
  }, [])

  const handleMouseMove = useCallback((e: React.MouseEvent) => {
    if (!isDragging || !previewRef.current) return
    const rect = previewRef.current.getBoundingClientRect()
    const x = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100))
    const y = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100))
    setTextX(Math.round(x))
    setTextY(Math.round(y))
  }, [isDragging])

  const handleMouseUp = useCallback(() => {
    setIsDragging(false)
  }, [])

  useEffect(() => {
    if (isDragging) {
      const handleGlobalMouseUp = () => setIsDragging(false)
      window.addEventListener('mouseup', handleGlobalMouseUp)
      return () => window.removeEventListener('mouseup', handleGlobalMouseUp)
    }
  }, [isDragging])

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2 mb-4">
        <MaterialIcon name="collections" className="w-5 h-5 text-cyan-400" size={20} />
        <h3 className="text-lg font-semibold text-white">과거 특집 표지 편집기</h3>
      </div>

      {loading ? (
        <div className="text-slate-400">로딩 중...</div>
      ) : seriesList.length === 0 ? (
        <div className="text-slate-400">시리즈가 없습니다. 뉴스 관리에서 시리즈를 먼저 생성하세요.</div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* 왼쪽: 시리즈 선택 및 설정 */}
          <div className="space-y-4">
            {/* 시리즈 선택 */}
            <div>
              <label className="block text-slate-400 text-sm mb-2">시리즈 선택</label>
              <select
                value={selectedSeriesId ?? ''}
                onChange={(e) => setSelectedSeriesId(e.target.value || null)}
                className="w-full bg-slate-800 text-white rounded-lg px-4 py-2 border border-slate-600"
              >
                <option value="">-- 시리즈 선택 --</option>
                {seriesList.map((s) => (
                  <option key={s.series_id} value={s.series_id}>
                    {s.series_title || '(제목 없음)'} ({s.article_count}개 기사)
                    {s.is_featured ? ' ★' : ''}
                  </option>
                ))}
              </select>
            </div>

            {selectedSeriesId && (
              <>
                {/* 표지 텍스트 */}
                <div>
                  <label className="block text-slate-400 text-sm mb-2">표지 텍스트</label>
                  <input
                    type="text"
                    value={coverText}
                    onChange={(e) => setCoverText(e.target.value)}
                    placeholder="예: 뉴욕타임즈 이란전쟁"
                    className="w-full bg-slate-800 text-white rounded-lg px-4 py-2 border border-slate-600"
                  />
                </div>

                {/* 색상 선택 */}
                <div>
                  <label className="block text-slate-400 text-sm mb-2">텍스트 색상</label>
                  <div className="flex items-center gap-3">
                    <input
                      type="color"
                      value={textColor}
                      onChange={(e) => setTextColor(e.target.value)}
                      className="w-12 h-10 rounded cursor-pointer"
                    />
                    <input
                      type="text"
                      value={textColor}
                      onChange={(e) => setTextColor(e.target.value)}
                      className="flex-1 bg-slate-800 text-white rounded-lg px-4 py-2 border border-slate-600 font-mono"
                    />
                  </div>
                </div>

                {/* 크기 조절 */}
                <div>
                  <label className="block text-slate-400 text-sm mb-2">텍스트 크기: {textSize}px</label>
                  <input
                    type="range"
                    min={12}
                    max={48}
                    value={textSize}
                    onChange={(e) => setTextSize(Number(e.target.value))}
                    className="w-full"
                  />
                </div>

                {/* 위치 조절 */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-slate-400 text-sm mb-2">X 위치: {textX}%</label>
                    <input
                      type="range"
                      min={0}
                      max={100}
                      value={textX}
                      onChange={(e) => setTextX(Number(e.target.value))}
                      className="w-full"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-2">Y 위치: {textY}%</label>
                    <input
                      type="range"
                      min={0}
                      max={100}
                      value={textY}
                      onChange={(e) => setTextY(Number(e.target.value))}
                      className="w-full"
                    />
                  </div>
                </div>

                {/* 과거 특집 노출 */}
                <div className="flex items-center gap-3">
                  <input
                    type="checkbox"
                    id="is_featured"
                    checked={isFeatured}
                    onChange={(e) => setIsFeatured(e.target.checked)}
                    className="w-5 h-5 rounded"
                  />
                  <label htmlFor="is_featured" className="text-white cursor-pointer">
                    과거 특집에 노출
                  </label>
                </div>

                {/* 정렬 순서 */}
                {isFeatured && (
                  <div>
                    <label className="block text-slate-400 text-sm mb-2">정렬 순서 (낮을수록 먼저)</label>
                    <input
                      type="number"
                      min={0}
                      value={displayOrder}
                      onChange={(e) => setDisplayOrder(Number(e.target.value))}
                      className="w-full bg-slate-800 text-white rounded-lg px-4 py-2 border border-slate-600"
                    />
                  </div>
                )}

                {/* 저장 버튼 */}
                <button
                  onClick={handleSave}
                  disabled={saving}
                  className="w-full px-4 py-3 bg-cyan-600 text-white rounded-lg hover:bg-cyan-500 disabled:opacity-50 font-medium"
                >
                  {saving ? '저장 중...' : '저장'}
                </button>
              </>
            )}
          </div>

          {/* 오른쪽: 미리보기 */}
          {selectedSeriesId && (
            <div>
              <label className="block text-slate-400 text-sm mb-2">
                미리보기 (드래그로 텍스트 위치 조정)
              </label>
              <div
                ref={previewRef}
                className="relative aspect-[3/4] w-full max-w-xs rounded-xl overflow-hidden shadow-lg cursor-move select-none"
                onMouseDown={handleMouseDown}
                onMouseMove={handleMouseMove}
                onMouseUp={handleMouseUp}
              >
                <img
                  src={imageUrl}
                  alt="표지 미리보기"
                  className="w-full h-full object-cover"
                  draggable={false}
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent pointer-events-none" />
                
                {coverText && (
                  <span
                    className="absolute font-bold leading-tight whitespace-pre-wrap text-center max-w-[90%] pointer-events-none"
                    style={{
                      color: textColor,
                      fontSize: `${textSize}px`,
                      left: `${textX}%`,
                      top: `${textY}%`,
                      transform: 'translate(-50%, -50%)',
                      textShadow: '0 2px 8px rgba(0,0,0,0.7)',
                    }}
                  >
                    {coverText}
                  </span>
                )}

                {selectedSeries?.series_title && (
                  <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/80 to-transparent pointer-events-none">
                    <h3 className="text-white text-lg font-semibold line-clamp-2 leading-snug">
                      {selectedSeries.series_title}
                    </h3>
                    {selectedSeries.article_count > 0 && (
                      <p className="text-white/80 text-sm mt-1">
                        {selectedSeries.article_count}개의 기사
                      </p>
                    )}
                  </div>
                )}
              </div>
              <p className="text-slate-500 text-xs mt-2">
                미리보기 위에서 마우스 드래그로 텍스트 위치를 조정할 수 있습니다.
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

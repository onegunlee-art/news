import { useState, useEffect, useCallback } from 'react'
import { adminFetch } from '../../../services/api'
import MaterialIcon from '../../Common/MaterialIcon'

interface ChangeItem {
  change_id: number
  title: string
  edition_date: string
  category: string
  has_video: boolean
  video_status: string | null
  project_id: number | null
}

interface Scene {
  id: number
  scene_num: number
  visual_type: string
  narration: string
  text_overlay: unknown
  visual_url?: string
  audio_url?: string
  duration_ms?: number
}

interface ProjectDetail {
  project: {
    id: number
    change_id: number
    title: string
    status: string
    error_message?: string
  }
  scenes: Scene[]
  video: {
    video_url?: string
    duration_sec?: number
    file_size_bytes?: number
  } | null
}

export default function YouTubePanel() {
  const [changes, setChanges] = useState<ChangeItem[]>([])
  const [selectedChange, setSelectedChange] = useState<ChangeItem | null>(null)
  const [projectDetail, setProjectDetail] = useState<ProjectDetail | null>(null)
  const [loading, setLoading] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))

  const loadChanges = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await adminFetch(`/api/admin/youtube/list.php?date=${date}`)
      const data = await res.json()
      if (data.success) {
        setChanges(data.items || [])
      } else {
        setError(data.error || '목록 로드 실패')
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '목록 로드 실패')
    } finally {
      setLoading(false)
    }
  }, [date])

  useEffect(() => {
    loadChanges()
  }, [loadChanges])

  const loadProjectDetail = async (changeId: number) => {
    try {
      const res = await adminFetch(`/api/admin/youtube/project.php?change_id=${changeId}`)
      const data = await res.json()
      if (data.success) {
        setProjectDetail(data)
      } else {
        setProjectDetail(null)
      }
    } catch {
      setProjectDetail(null)
    }
  }

  const handleSelectChange = async (item: ChangeItem) => {
    setSelectedChange(item)
    setProjectDetail(null)
    if (item.has_video && item.project_id) {
      await loadProjectDetail(item.change_id)
    }
  }

  const handleGenerate = async () => {
    if (!selectedChange) return
    
    setGenerating(true)
    setError(null)
    try {
      const res = await adminFetch('/api/admin/youtube/generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ change_id: selectedChange.change_id }),
      })
      const data = await res.json()
      if (data.success) {
        await loadChanges()
        await loadProjectDetail(selectedChange.change_id)
      } else {
        setError(data.error || '영상 생성 실패')
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : '영상 생성 실패')
    } finally {
      setGenerating(false)
    }
  }

  return (
    <div className="p-6 max-w-6xl">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold flex items-center gap-2">
          <MaterialIcon name="smart_display" className="text-red-500" />
          유튜브 검수
        </h2>
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className="border rounded px-3 py-1.5 text-sm"
        />
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Change List */}
        <div className="lg:col-span-1 bg-white rounded-lg border p-4">
          <h3 className="font-medium mb-3 text-gray-700">오늘의 발견 뉴스</h3>
          {loading ? (
            <div className="text-sm text-gray-500">로딩 중...</div>
          ) : changes.length === 0 ? (
            <div className="text-sm text-gray-500">뉴스가 없습니다</div>
          ) : (
            <div className="space-y-2">
              {changes.map((item) => (
                <button
                  key={item.change_id}
                  onClick={() => handleSelectChange(item)}
                  className={`w-full text-left p-3 rounded-lg border transition ${
                    selectedChange?.change_id === item.change_id
                      ? 'border-blue-500 bg-blue-50'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                      <div className="font-medium text-sm truncate">{item.title}</div>
                      <div className="text-xs text-gray-500 mt-1">
                        {item.edition_date}
                      </div>
                    </div>
                    {item.has_video && (
                      <span className="ml-2 px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">
                        완료
                      </span>
                    )}
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Right: Selected Change / Video */}
        <div className="lg:col-span-2 space-y-4">
          {selectedChange ? (
            <>
              {/* Header */}
              <div className="bg-white rounded-lg border p-4">
                <h3 className="font-bold text-lg mb-2">{selectedChange.title}</h3>
                <div className="flex items-center gap-4 text-sm text-gray-600">
                  <span>ID: {selectedChange.change_id}</span>
                  <span>날짜: {selectedChange.edition_date}</span>
                  {selectedChange.video_status && (
                    <span className={`px-2 py-0.5 rounded ${
                      selectedChange.video_status === 'rendered' 
                        ? 'bg-green-100 text-green-700' 
                        : 'bg-yellow-100 text-yellow-700'
                    }`}>
                      {selectedChange.video_status}
                    </span>
                  )}
                </div>
                <button
                  onClick={handleGenerate}
                  disabled={generating}
                  className="mt-4 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50 flex items-center gap-2"
                >
                  <MaterialIcon name={generating ? 'hourglass_empty' : 'play_arrow'} />
                  {generating ? '생성 중...' : selectedChange.has_video ? '재생성' : '영상 생성'}
                </button>
              </div>

              {/* Video Preview */}
              {projectDetail?.video?.video_url && (
                <div className="bg-black rounded-lg overflow-hidden">
                  <video
                    controls
                    className="w-full max-h-[600px] mx-auto"
                    src={projectDetail.video.video_url}
                  >
                    브라우저가 비디오를 지원하지 않습니다.
                  </video>
                  <div className="p-3 bg-gray-900 text-white text-sm flex justify-between">
                    <span>
                      길이: {projectDetail.video.duration_sec}초
                    </span>
                    <a
                      href={projectDetail.video.video_url}
                      download
                      className="text-blue-400 hover:underline"
                    >
                      다운로드
                    </a>
                  </div>
                </div>
              )}

              {/* Scenes Table */}
              {projectDetail?.scenes && projectDetail.scenes.length > 0 && (
                <div className="bg-white rounded-lg border overflow-hidden">
                  <h4 className="font-medium p-4 border-b bg-gray-50">6장면 대본</h4>
                  <div className="divide-y">
                    {projectDetail.scenes.map((scene) => (
                      <div key={scene.id} className="p-4 flex gap-4">
                        <div className="w-24 flex-shrink-0">
                          {scene.visual_url ? (
                            <img
                              src={scene.visual_url}
                              alt={`Scene ${scene.scene_num}`}
                              className="w-full aspect-[9/16] object-cover rounded bg-gray-100"
                            />
                          ) : (
                            <div className="w-full aspect-[9/16] bg-gray-200 rounded flex items-center justify-center text-gray-400">
                              <MaterialIcon name="image" />
                            </div>
                          )}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 mb-1">
                            <span className="font-medium">장면 {scene.scene_num}</span>
                            <span className="text-xs px-2 py-0.5 bg-gray-100 rounded">
                              {scene.visual_type}
                            </span>
                            {scene.duration_ms && (
                              <span className="text-xs text-gray-500">
                                {(scene.duration_ms / 1000).toFixed(1)}초
                              </span>
                            )}
                          </div>
                          <p className="text-sm text-gray-600 line-clamp-3">
                            {scene.narration || '(나레이션 없음)'}
                          </p>
                          {scene.audio_url && (
                            <audio controls className="mt-2 h-8 w-full max-w-xs">
                              <source src={scene.audio_url} type="audio/wav" />
                            </audio>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </>
          ) : (
            <div className="bg-gray-50 rounded-lg border border-dashed p-12 text-center text-gray-500">
              왼쪽에서 뉴스를 선택하세요
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

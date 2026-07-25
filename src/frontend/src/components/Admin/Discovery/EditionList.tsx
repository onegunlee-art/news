import type { DiscoveryEdition } from './types'

interface Props {
  editions: DiscoveryEdition[]
  selectedDate: string | null
  onSelect: (date: string) => void
  onGenerate: () => void
  onPublish: (edition: DiscoveryEdition) => void
  loading: boolean
}

const statusLabel: Record<string, string> = {
  generating: '생성 중',
  draft: '초안',
  published: '발행됨',
}

export default function EditionList({
  editions,
  selectedDate,
  onSelect,
  onGenerate,
  onPublish,
  loading,
}: Props) {
  return (
    <div className="discovery-card">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
        <h3 style={{ margin: 0 }}>에디션</h3>
        <button type="button" className="discovery-btn" onClick={onGenerate} disabled={loading}>
          지금 생성
        </button>
      </div>
      {editions.length === 0 && <p style={{ color: '#666', fontSize: 13 }}>아직 에디션이 없습니다.</p>}
      {editions.map((edition) => (
        <div
          key={edition.id}
          className={`discovery-edition-item${selectedDate === edition.edition_date ? ' selected' : ''}`}
          onClick={() => onSelect(edition.edition_date)}
          onKeyDown={(e) => e.key === 'Enter' && onSelect(edition.edition_date)}
          role="button"
          tabIndex={0}
        >
          <div>
            <div>{edition.edition_date}</div>
            <div style={{ fontSize: 12, color: '#666' }}>
              {statusLabel[edition.status] ?? edition.status} · {edition.change_count}개
            </div>
            {edition.warning_message && (
              <div style={{ fontSize: 11, color: '#b45309' }}>{edition.warning_message}</div>
            )}
          </div>
          {edition.status === 'draft' && (
            <button
              type="button"
              className="discovery-btn discovery-btn-secondary"
              onClick={(e) => {
                e.stopPropagation()
                onPublish(edition)
              }}
            >
              발행
            </button>
          )}
        </div>
      ))}
    </div>
  )
}

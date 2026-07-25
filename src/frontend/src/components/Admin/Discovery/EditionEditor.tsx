import { useState } from 'react'
import SourceVerificationStatus from './SourceVerificationStatus'
import type { DiscoveryChange } from './types'

interface Props {
  changes: DiscoveryChange[]
  onSave: (change: DiscoveryChange) => Promise<void>
  onDelete: (id: number) => Promise<void>
}

const categoryLabels: Record<string, string> = {
  geopolitics: '지정학',
  business: '비즈니스',
  tech: '기술',
  climate: '기후',
  other: '기타',
}

export default function EditionEditor({ changes, onSave, onDelete }: Props) {
  const [editingId, setEditingId] = useState<number | null>(null)
  const [draft, setDraft] = useState<DiscoveryChange | null>(null)

  const startEdit = (change: DiscoveryChange) => {
    setEditingId(change.id)
    setDraft({ ...change, briefing: { ...change.briefing } })
  }

  const save = async () => {
    if (!draft) return
    await onSave(draft)
    setEditingId(null)
    setDraft(null)
  }

  if (!changes.length) {
    return (
      <div className="discovery-card">
        <p style={{ margin: 0, color: '#666' }}>검수할 Change가 없습니다.</p>
      </div>
    )
  }

  return (
    <div className="discovery-card">
      <h3 style={{ marginTop: 0 }}>검수 편집</h3>
      {changes.map((change) => (
        <div key={change.id} style={{ borderTop: '1px solid #eee', paddingTop: 12, marginTop: 12 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
            <div>
              <span className="discovery-category">{categoryLabels[change.category] ?? change.category}</span>
              <strong> #{change.rank}</strong>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button type="button" className="discovery-btn discovery-btn-secondary" onClick={() => startEdit(change)}>
                수정
              </button>
              <button type="button" className="discovery-btn discovery-btn-secondary" onClick={() => onDelete(change.id)}>
                삭제
              </button>
            </div>
          </div>

          {editingId === change.id && draft ? (
            <div style={{ marginTop: 8 }}>
              <input
                value={draft.title}
                onChange={(e) => setDraft({ ...draft, title: e.target.value })}
                style={{ width: '100%', marginBottom: 6 }}
              />
              <textarea
                value={draft.summary}
                onChange={(e) => setDraft({ ...draft, summary: e.target.value })}
                rows={3}
                style={{ width: '100%', marginBottom: 6 }}
              />
              {(['what_changed', 'why_changed', 'why_important', 'future_impact'] as const).map((key) => (
                <textarea
                  key={key}
                  value={draft.briefing[key]}
                  onChange={(e) => setDraft({ ...draft, briefing: { ...draft.briefing, [key]: e.target.value } })}
                  rows={2}
                  placeholder={key}
                  style={{ width: '100%', marginBottom: 4, fontSize: 12 }}
                />
              ))}
              <button type="button" className="discovery-btn" onClick={save}>저장</button>
            </div>
          ) : (
            <>
              <h4 style={{ margin: '8px 0 4px' }}>{change.title}</h4>
              <p style={{ margin: 0, fontSize: 13, color: '#444' }}>{change.summary}</p>
            </>
          )}

          <div style={{ marginTop: 8 }}>
            <div style={{ fontSize: 12, fontWeight: 600, marginBottom: 4 }}>출처</div>
            <SourceVerificationStatus sources={change.sources} />
          </div>
        </div>
      ))}
    </div>
  )
}

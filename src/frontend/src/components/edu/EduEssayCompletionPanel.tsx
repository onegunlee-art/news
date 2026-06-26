import { useState } from 'react'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import EduStructureReviewCard from './EduStructureReviewCard'
import EssayRevealWrapper from './EssayRevealWrapper'
import type { EssayArtifact } from './EssayRevealCard'
import type { EssayStructurePreview } from './StructurePreviewCard'

type CompletionView = 'essay' | 'structure'

type Props = {
  essay: EssayArtifact
  structure: EssayStructurePreview | null
  onChange: (essay: EssayArtifact) => void
  disabled?: boolean
  authorName?: string | null
  playReveal: boolean
  onRevealComplete?: () => void
  saveStatus?: 'idle' | 'saving' | 'saved' | 'error'
  stanceChanged?: boolean
}

/** 완주 — 내래이션 글(기본) + 구조 보기 토글 */
export default function EduEssayCompletionPanel({
  essay,
  structure,
  onChange,
  disabled,
  authorName,
  playReveal,
  onRevealComplete,
  saveStatus = 'idle',
  stanceChanged = false,
}: Props) {
  const [view, setView] = useState<CompletionView>('essay')
  const canShowStructure = Boolean(structure?.sections?.length)

  return (
    <section className="space-y-4 pt-2">
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <div
          className="inline-flex rounded-full border-2 p-0.5"
          style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
          role="tablist"
          aria-label="완성글 보기 방식"
        >
          <button
            type="button"
            role="tab"
            aria-selected={view === 'essay'}
            className={`rounded-full px-3 py-1.5 font-bold transition-colors ${eduGameClasses.textKo}`}
            style={{
              fontSize: eduGame.fontSize.caption,
              backgroundColor: view === 'essay' ? eduGame.primary : 'transparent',
              color: view === 'essay' ? eduGame.bg : eduGame.ink,
            }}
            onClick={() => setView('essay')}
          >
            나만의 글
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={view === 'structure'}
            disabled={!canShowStructure}
            className={`rounded-full px-3 py-1.5 font-bold transition-colors disabled:opacity-40 ${eduGameClasses.textKo}`}
            style={{
              fontSize: eduGame.fontSize.caption,
              backgroundColor: view === 'structure' ? eduGame.primary : 'transparent',
              color: view === 'structure' ? eduGame.bg : eduGame.ink,
            }}
            onClick={() => setView('structure')}
          >
            구조 보기
          </button>
        </div>
        <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
          {saveStatus === 'saving' && '저장 중…'}
          {saveStatus === 'saved' && '✓ 자동 저장됨'}
          {saveStatus === 'error' && '저장 실패 — 다시 시도해줘'}
        </span>
      </div>

      {stanceChanged && view === 'essay' && (
        <span
          className="inline-block font-bold px-3 py-1 rounded-full"
          style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primaryDark, backgroundColor: eduGame.primaryLight }}
        >
          생각이 바뀌었다
        </span>
      )}

      {view === 'structure' && structure ? (
        <EduStructureReviewCard structure={structure} />
      ) : (
        <EssayRevealWrapper
          essay={essay}
          onChange={onChange}
          disabled={disabled}
          authorName={authorName}
          playReveal={playReveal}
          onRevealComplete={onRevealComplete}
        />
      )}
    </section>
  )
}

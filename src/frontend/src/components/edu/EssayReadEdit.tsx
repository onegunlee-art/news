import { useCallback, useRef, useState, type ReactNode } from 'react'
import { EDU_BRAND } from '../../constants/eduBrand'
import { paragraphsToText, textToParagraphs, updateSectionAt, isEssayNarrationMode, rebuildNarrationFullText } from './essayUtils'
import type { EssayArtifact } from './EssayRevealCard'

interface EssayReadEditProps {
  essay: EssayArtifact
  onChange: (essay: EssayArtifact) => void
  disabled?: boolean
  authorName?: string | null
}

type EditField =
  | 'title'
  | 'subtitle'
  | 'hero'
  | 'full_text'
  | 'body-paragraphs'
  | 'conclusion-heading'
  | 'conclusion-body'
  | `section-${number}-heading`
  | `section-${number}-body`

const editAreaClass =
  'w-full rounded-lg px-3 py-2 text-base leading-relaxed focus:outline-none focus:ring-2 resize-y'
const readTapClass =
  'w-full text-left rounded-lg transition-colors hover:bg-black/[0.03] active:bg-black/[0.05] cursor-text group relative'

export default function EssayReadEdit({
  essay,
  onChange,
  disabled = false,
  authorName,
}: EssayReadEditProps) {
  const [editing, setEditing] = useState<EditField | null>(null)
  const draftRef = useRef('')

  const hasStructure = (essay.sections?.length ?? 0) > 0
  const narration = isEssayNarrationMode(essay)

  const update = useCallback(
    (patch: Partial<EssayArtifact>) => {
      onChange({ ...essay, ...patch })
    },
    [essay, onChange]
  )

  const startEdit = (field: EditField, value: string) => {
    if (disabled) return
    draftRef.current = value
    setEditing(field)
  }

  const commitEdit = (field: EditField) => {
    const value = draftRef.current
    setEditing(null)

    if (field === 'title') update({ title: value })
    else if (field === 'subtitle') update({ subtitle: value })
    else if (field === 'hero') update({ hero_sentence: value })
    else if (field === 'full_text') update({ full_text: value })
    else if (field === 'body-paragraphs') {
      const bodyParagraphs = textToParagraphs(value)
      update({
        body_paragraphs: bodyParagraphs,
        narration_mode: true,
        sections: [],
        conclusion_paragraphs: [],
        full_text: rebuildNarrationFullText({ ...essay, body_paragraphs: bodyParagraphs }),
      })
    } else if (field === 'conclusion-heading') update({ conclusion_heading: value })
    else if (field === 'conclusion-body') {
      update({ conclusion_paragraphs: textToParagraphs(value) })
    } else if (field.startsWith('section-')) {
      const match = field.match(/^section-(\d+)-(heading|body)$/)
      if (!match) return
      const index = Number(match[1])
      const kind = match[2]
      const sections = essay.sections ?? []
      if (kind === 'heading') {
        update({ sections: updateSectionAt(sections, index, { heading: value }) })
      } else {
        update({ sections: updateSectionAt(sections, index, { paragraphs: textToParagraphs(value) }) })
      }
    }
  }

  const cancelEdit = () => setEditing(null)

  const EditShell = ({
    field,
    value,
    multiline,
    rows,
    read,
    className = '',
  }: {
    field: EditField
    value: string
    multiline?: boolean
    rows?: number
    read: ReactNode
    className?: string
  }) => {
    const isEditing = editing === field

    if (isEditing) {
      return (
        <div className={`space-y-2 ${className}`}>
          {multiline ? (
            <textarea
              autoFocus
              defaultValue={value}
              rows={rows ?? 4}
              disabled={disabled}
              className={editAreaClass}
              style={{
                backgroundColor: EDU_BRAND.accentBg,
                borderColor: EDU_BRAND.accent,
                boxShadow: `0 0 0 2px ${EDU_BRAND.accent}33`,
              }}
              onChange={(e) => {
                draftRef.current = e.target.value
              }}
              onKeyDown={(e) => {
                if (e.key === 'Escape') cancelEdit()
              }}
            />
          ) : (
            <input
              autoFocus
              type="text"
              defaultValue={value}
              disabled={disabled}
              className={editAreaClass}
              style={{
                backgroundColor: EDU_BRAND.accentBg,
                boxShadow: `0 0 0 2px ${EDU_BRAND.accent}33`,
              }}
              onChange={(e) => {
                draftRef.current = e.target.value
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') commitEdit(field)
                if (e.key === 'Escape') cancelEdit()
              }}
            />
          )}
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => commitEdit(field)}
              className="text-xs font-bold px-3 py-1.5 rounded-full text-white"
              style={{ backgroundColor: EDU_BRAND.accent }}
            >
              완료
            </button>
            <button
              type="button"
              onClick={cancelEdit}
              className="text-xs px-3 py-1.5 rounded-full"
              style={{ color: EDU_BRAND.muted }}
            >
              취소
            </button>
          </div>
        </div>
      )
    }

    return (
      <button
        type="button"
        disabled={disabled}
        onClick={() => startEdit(field, value)}
        className={`${readTapClass} ${className}`}
      >
        {read}
        {!disabled && (
          <span
            className="absolute top-1 right-2 text-[10px] opacity-0 group-hover:opacity-100 pointer-events-none"
            style={{ color: EDU_BRAND.muted }}
          >
            탭해서 수정
          </span>
        )}
      </button>
    )
  }

  if (narration) {
    const bodyText = paragraphsToText(essay.body_paragraphs ?? textToParagraphs(essay.full_text ?? ''))
    return (
      <article className="space-y-8">
        <EditShell
          field="title"
          value={essay.title ?? ''}
          read={
            <h2 className="text-2xl sm:text-3xl font-bold leading-tight tracking-tight py-1 pr-16">
              {essay.title || '제목'}
            </h2>
          }
        />

        {(essay.subtitle || editing === 'subtitle') && (
          <EditShell
            field="subtitle"
            value={essay.subtitle ?? ''}
            multiline
            rows={2}
            read={
              <p className="text-base leading-relaxed py-1 pr-16" style={{ color: EDU_BRAND.muted }}>
                {essay.subtitle}
              </p>
            }
          />
        )}

        <EditShell
          field="body-paragraphs"
          value={bodyText}
          multiline
          rows={12}
          read={
            <div className="space-y-5 py-1 pr-16">
              {(essay.body_paragraphs ?? textToParagraphs(essay.full_text ?? '')).map((p, j) => (
                <p key={j} className="text-base leading-[1.75] text-[#333] whitespace-pre-wrap">
                  {p}
                </p>
              ))}
            </div>
          }
        />

        <EditShell
          field="hero"
          value={essay.hero_sentence ?? ''}
          multiline
          rows={2}
          read={
            <blockquote
              className="text-lg leading-snug italic py-4 px-5 rounded-xl pr-16"
              style={{
                color: EDU_BRAND.ink,
                backgroundColor: EDU_BRAND.accentBg,
                borderLeft: `4px solid ${EDU_BRAND.accent}`,
              }}
            >
              {essay.hero_sentence || '핵심 문장'}
            </blockquote>
          }
        />

        {authorName && (
          <footer
            className="text-right text-sm pt-4 mt-2"
            style={{ color: EDU_BRAND.muted, borderTop: `1px solid ${EDU_BRAND.border}` }}
          >
            by {authorName}
          </footer>
        )}
      </article>
    )
  }

  if (!hasStructure) {
    return (
      <div className="space-y-6">
        <EditShell
          field="full_text"
          value={essay.full_text ?? ''}
          multiline
          rows={14}
          read={
            <p className="text-base leading-[1.75] text-[#333] whitespace-pre-wrap py-1">
              {essay.full_text || '본문을 탭해서 작성해보세요.'}
            </p>
          }
        />
        <EditShell
          field="hero"
          value={essay.hero_sentence ?? ''}
          multiline
          rows={2}
          className="rounded-xl p-4"
          read={
            <blockquote
              className="text-base italic py-1"
              style={{ color: EDU_BRAND.ink }}
            >
              {essay.hero_sentence || '핵심 문장을 탭해서 적어보세요.'}
            </blockquote>
          }
        />
        {authorName && (
          <footer className="text-right text-sm pt-4" style={{ color: EDU_BRAND.muted }}>
            by {authorName}
          </footer>
        )}
      </div>
    )
  }

  return (
    <article className="space-y-8">
      <EditShell
        field="title"
        value={essay.title ?? ''}
        read={
          <h2 className="text-2xl sm:text-3xl font-bold leading-tight tracking-tight py-1 pr-16">
            {essay.title || '제목'}
          </h2>
        }
      />

      {(essay.subtitle || editing === 'subtitle') && (
        <EditShell
          field="subtitle"
          value={essay.subtitle ?? ''}
          multiline
          rows={2}
          read={
            <p className="text-base leading-relaxed py-1 pr-16" style={{ color: EDU_BRAND.muted }}>
              {essay.subtitle}
            </p>
          }
        />
      )}

      {(essay.sections ?? []).map((sec, i) => (
        <section key={`sec-${i}`} className="space-y-3">
          <EditShell
            field={`section-${i}-heading`}
            value={sec.heading}
            read={
              <h3
                className="text-sm font-bold tracking-wide py-1 pr-16"
                style={{
                  color: EDU_BRAND.accent,
                  borderLeft: `3px solid ${EDU_BRAND.accent}`,
                  paddingLeft: '0.75rem',
                }}
              >
                {sec.heading}
              </h3>
            }
          />
          <EditShell
            field={`section-${i}-body`}
            value={paragraphsToText(sec.paragraphs)}
            multiline
            rows={6}
            read={
              <div className="space-y-4 py-1 pr-16">
                {(sec.paragraphs ?? []).map((p, j) => (
                  <p key={j} className="text-base leading-[1.75] text-[#333]">
                    {p}
                  </p>
                ))}
              </div>
            }
          />
        </section>
      ))}

      <section
        className="space-y-3 pt-2"
        style={{ borderTop: `1px solid ${EDU_BRAND.border}`, paddingTop: '1.5rem' }}
      >
        <EditShell
          field="conclusion-heading"
          value={essay.conclusion_heading ?? '결론'}
          read={<h3 className="text-base font-bold py-1 pr-16">{essay.conclusion_heading ?? '결론'}</h3>}
        />
        <EditShell
          field="conclusion-body"
          value={paragraphsToText(essay.conclusion_paragraphs)}
          multiline
          rows={5}
          read={
            <div className="space-y-4 py-1 pr-16">
              {(essay.conclusion_paragraphs ?? []).map((p, j) => (
                <p key={j} className="text-base leading-[1.75] text-[#333]">
                  {p}
                </p>
              ))}
            </div>
          }
        />
      </section>

      <EditShell
        field="hero"
        value={essay.hero_sentence ?? ''}
        multiline
        rows={2}
        read={
          <blockquote
            className="text-lg leading-snug italic py-4 px-5 rounded-xl pr-16"
            style={{
              color: EDU_BRAND.ink,
              backgroundColor: EDU_BRAND.accentBg,
              borderLeft: `4px solid ${EDU_BRAND.accent}`,
            }}
          >
            {essay.hero_sentence || '핵심 문장'}
          </blockquote>
        }
      />

      {authorName && (
        <footer
          className="text-right text-sm pt-4 mt-2"
          style={{ color: EDU_BRAND.muted, borderTop: `1px solid ${EDU_BRAND.border}` }}
        >
          by {authorName}
        </footer>
      )}
    </article>
  )
}

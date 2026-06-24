import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EssayStructurePreview } from './StructurePreviewCard'

const ROLE_LABEL: Record<string, string> = {
  background: '배경',
  tension: '갈등',
  stance: '입장',
  counter: '반론',
}

/** 완주 화면 — "네가 이렇게 따졌어" 구조 요약 (eduGame). */
export default function EduStructureReviewCard({ structure }: { structure: EssayStructurePreview }) {
  if (!structure.sections?.length) return null

  return (
    <section
      className={`rounded-2xl border-2 p-4 space-y-3 mb-4 ${eduGameClasses.textKo}`}
      style={{ borderColor: eduGame.primaryLight, backgroundColor: eduGame.bg }}
    >
      <p className="font-bold" style={{ fontSize: eduGame.fontSize.bodyLg, color: eduGame.primaryDark }}>
        네가 이렇게 따졌어
      </p>
      {structure.title && (
        <h2 className="font-bold" style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}>
          {structure.title}
        </h2>
      )}
      {structure.student_stance && (
        <span
          className="inline-block rounded-full px-3 py-1 font-bold"
          style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primaryDark, backgroundColor: eduGame.primaryLight }}
        >
          {structure.student_stance}
        </span>
      )}
      <div className="space-y-2">
        {structure.sections.map((sec, i) => (
          <div
            key={`${sec.heading}-${i}`}
            className="rounded-xl border-2 p-3"
            style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
          >
            <div className="flex items-center gap-2 mb-1.5 flex-wrap">
              <p className="font-bold" style={{ fontSize: eduGame.fontSize.label, color: eduGame.ink }}>
                {sec.heading}
              </p>
              {sec.role && (
                <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                  {ROLE_LABEL[sec.role] ?? sec.role}
                </span>
              )}
            </div>
            <ul className="list-disc list-inside space-y-1" style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink, lineHeight: eduGame.lineHeight.body }}>
              {(sec.bullets ?? []).map((b, j) => (
                <li key={j}>{b}</li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      {(structure.conclusion_bullets?.length ?? 0) > 0 && (
        <div className="rounded-xl border-2 p-3" style={{ borderColor: eduGame.primaryLight, backgroundColor: eduGame.primaryLight }}>
          <p className="font-bold mb-1.5" style={{ fontSize: eduGame.fontSize.label, color: eduGame.primaryDark }}>
            {structure.conclusion_heading ?? '결론'}
          </p>
          <ul className="list-disc list-inside space-y-1" style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink, lineHeight: eduGame.lineHeight.body }}>
            {structure.conclusion_bullets!.map((b, j) => (
              <li key={j}>{b}</li>
            ))}
          </ul>
        </div>
      )}
    </section>
  )
}

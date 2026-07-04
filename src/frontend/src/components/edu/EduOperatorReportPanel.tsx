import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduParentReportPayload } from '../../services/eduOperatorApi'

type Props = {
  report: EduParentReportPayload
  loadingPdf: boolean
  onShare: () => void
  onDownload: () => void
}

export default function EduOperatorReportPanel({ report, loadingPdf, onShare, onDownload }: Props) {
  return (
    <div className="space-y-4">
      <div className="rounded-xl p-4 text-white" style={{ backgroundColor: eduGame.ink }}>
        <p className="text-xs opacity-80 mb-1">● gistudy</p>
        <h3 className="text-xl font-bold leading-snug">{report.cover.headline}</h3>
        <p className="text-sm mt-2 opacity-90">
          {report.student_name} · {report.grade_label}
        </p>
      </div>

      <div>
        <p className="text-xs font-bold mb-1" style={{ color: eduGame.primary }}>
          코치의 편지
        </p>
        {report.coach_letter.paragraphs.map((p, i) => (
          <p key={i} className={`text-sm mb-2 ${eduGameClasses.textKo}`} style={{ lineHeight: 1.65 }}>
            {p}
          </p>
        ))}
        {report.coach_letter.fallback && (
          <p className="text-xs" style={{ color: eduGame.muted }}>
            ※ LLM 대신 기본 문구 사용
          </p>
        )}
      </div>

      {report.before_after && (
        <div>
          <p className="text-xs font-bold mb-2" style={{ color: eduGame.primary }}>
            생각이 자란 순간
          </p>
          <div className="grid gap-2 sm:grid-cols-2 text-sm">
            <div className="rounded-lg border p-2" style={{ borderColor: eduGame.border }}>
              <p className="text-xs" style={{ color: eduGame.muted }}>
                {report.before_after.before_label}
              </p>
              <p className="font-bold mt-1">&ldquo;{report.before_after.before_text}&rdquo;</p>
            </div>
            <div
              className="rounded-lg border p-2"
              style={{ borderColor: eduGame.primary, backgroundColor: eduGame.primaryLight }}
            >
              <p className="text-xs" style={{ color: eduGame.primary }}>
                {report.before_after.after_label}
              </p>
              <p className="font-bold mt-1">&ldquo;{report.before_after.after_text}&rdquo;</p>
            </div>
          </div>
        </div>
      )}

      {report.student_quote && (
        <blockquote
          className="border-l-4 pl-3 text-sm font-bold"
          style={{ borderColor: eduGame.primary, lineHeight: 1.55 }}
        >
          &ldquo;{report.student_quote}&rdquo;
        </blockquote>
      )}

      <div className="flex flex-wrap gap-1.5">
        {report.topic_tags.map((tag) => (
          <span
            key={tag}
            className="text-xs font-bold px-2 py-1 rounded-full border"
            style={{ borderColor: eduGame.primary }}
          >
            {tag.length > 28 ? `${tag.slice(0, 28)}…` : tag}
          </span>
        ))}
      </div>

      <div className="flex gap-4 text-center pt-2 border-t" style={{ borderColor: eduGame.border }}>
        <div className="flex-1">
          <div className="text-2xl font-bold" style={{ color: eduGame.primary }}>
            {report.stats.completed_count}
          </div>
          <div className="text-xs" style={{ color: eduGame.muted }}>
            완주
          </div>
        </div>
        <div className="flex-1">
          <div className="text-2xl font-bold" style={{ color: eduGame.primary }}>
            {report.stats.streak_days}
          </div>
          <div className="text-xs" style={{ color: eduGame.muted }}>
            연속(일)
          </div>
        </div>
        <div className="flex-1">
          <div className="text-lg font-bold" style={{ color: eduGame.primary }}>
            {report.stats.coach_label_ko}
          </div>
          <div className="text-xs" style={{ color: eduGame.muted }}>
            사고력
          </div>
        </div>
      </div>

      <div className="pt-2 space-y-2">
        <button
          type="button"
          disabled={loadingPdf}
          onClick={onShare}
          className={`w-full py-3.5 ${eduGameClasses.btnPrimary} touch-manipulation`}
          style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
        >
          {loadingPdf ? 'PDF 만드는 중…' : '리포트 공유하기'}
        </button>
        <button
          type="button"
          disabled={loadingPdf}
          onClick={onDownload}
          className="w-full py-2.5 rounded-xl border text-sm font-bold touch-manipulation"
          style={{ borderColor: eduGame.border, color: eduGame.muted }}
        >
          PDF 저장 (다운로드)
        </button>
      </div>
    </div>
  )
}

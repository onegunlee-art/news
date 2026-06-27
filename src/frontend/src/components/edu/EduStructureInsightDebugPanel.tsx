import type { EduStructureInsightDebug } from '../../constants/eduInsightDebug'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

type Props = {
  insight: EduStructureInsightDebug | null
  loading?: boolean
}

/** 완주 후 내부 진단 — ?insight_debug=1 일 때만 표시 */
export default function EduStructureInsightDebugPanel({ insight, loading = false }: Props) {
  if (loading) {
    return (
      <section
        className="rounded-lg border border-dashed px-3 py-2 text-xs font-mono"
        style={{ borderColor: eduGame.border, color: eduGame.muted }}
      >
        진단 로딩…
      </section>
    )
  }
  if (!insight) {
    return (
      <section
        className="rounded-lg border border-dashed px-3 py-2 text-xs font-mono"
        style={{ borderColor: eduGame.border, color: eduGame.muted }}
      >
        structure_insight 없음 (서버 EDU_INSIGHT_DEBUG_STUDENT_IDS 확인)
      </section>
    )
  }

  const level = insight.exploration_depth_level ?? '-'
  const mode = insight.diagnose_mode ?? '-'
  const ver = insight.diagnose_version ?? '-'
  const tension = insight.tension_engaged ?? '-'
  const axes =
    insight.axes_engaged_count != null && insight.axes_total != null
      ? `${insight.axes_engaged_count}/${insight.axes_total}`
      : '-'

  return (
    <section
      className="rounded-lg border px-3 py-2 space-y-1 font-mono text-xs"
      style={{ borderColor: '#f59e0b', backgroundColor: '#fffbeb', color: '#92400e' }}
      aria-label="구조 진단 내부 디버그"
    >
      <p className={`font-bold ${eduGameClasses.textKo}`} style={{ fontSize: '0.75rem' }}>
        내부 진단 (insight_debug)
      </p>
      <p>
        saved={insight.saved ? 'yes' : 'no'} · mode={mode} · L={level} · ver={ver}
      </p>
      <p>
        tension={tension} · axes={axes} · evidence={insight.evidence_linked ?? '-'}
      </p>
      {insight.fallback_reason ? <p>fallback={insight.fallback_reason}</p> : null}
      {insight.structure_note ? (
        <p className={`${eduGameClasses.textKo} opacity-90`}>{insight.structure_note}</p>
      ) : null}
    </section>
  )
}

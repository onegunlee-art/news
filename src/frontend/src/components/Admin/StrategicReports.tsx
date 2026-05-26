import { useCallback, useEffect, useState, type ReactNode, type FormEvent } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import { adminFetch } from '../../services/api';

const API = `${import.meta.env.VITE_API_URL || '/api'}/admin/strategic-reports.php`;

interface EmailModalState {
  open: boolean;
  to: string;
  subject: string;
  message: string;
  sending: boolean;
  error: string;
  success: string;
}

type ReportStatus = 'draft' | 'reviewed' | 'approved';

interface StructuralShift {
  headline?: string;
  from_pattern?: string;
  to_pattern?: string;
  why_now?: string;
  evidence_source_ids?: number[];
}

interface NarrativeCollision {
  label?: string;
  actor_a?: string;
  view_a?: string;
  actor_b?: string;
  view_b?: string;
  collision?: string;
  source_ids?: number[];
}

interface ScqaReport {
  core_question?: string;
  executive_summary?: string;
  structural_shift?: StructuralShift;
  situation?: {
    narrative?: string;
    timeline?: Array<{ date: string; event: string; source_id: number }>;
    anchor_entities?: string[];
  };
  complication?: {
    trigger?: string;
    narrative_collisions?: NarrativeCollision[];
    perspectives?: Array<{ viewpoint: string; source_id: number; quote?: string }>;
  };
  question?: string;
  answer?: {
    implication?: string;
    why_it_matters_chain?: string[];
    scenarios?: Array<{ type: string; probability: number; outcome: string; prediction_signal?: string }>;
    action_matrix?: { watch?: string[]; consider?: string[]; act?: string[] };
  };
  meta?: { language?: string; confidence?: string };
}

interface EditDiffItem {
  path: string;
  before: unknown;
  after: unknown;
}

interface ReportDetail extends ReportListItem {
  scqa_raw_json?: ScqaReport;
  scqa_edited_json?: ScqaReport | null;
  edit_diff_json?: EditDiffItem[] | null;
  edit_reason?: string | null;
  editor_notes?: string | null;
  judgment_feedbacks?: unknown;
  meta_json?: Record<string, unknown> | null;
  source_articles_json?: Array<{ id: number; title: string; source_api: string; url: string }>;
}

interface ReportListItem {
  id: number;
  report_week: string;
  period_start: string;
  period_end: string;
  status: ReportStatus;
  confidence: string;
  executive_summary: string | null;
  created_at: string;
}

const statusStyle: Record<ReportStatus, string> = {
  draft: 'bg-slate-500/20 text-slate-300',
  reviewed: 'bg-amber-500/20 text-amber-400',
  approved: 'bg-emerald-500/20 text-emerald-400',
};

const statusLabel: Record<ReportStatus, string> = {
  draft: '초안',
  reviewed: '검토됨',
  approved: '승인됨',
};

export default function StrategicReports() {
  const [reports, setReports] = useState<ReportListItem[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<ReportDetail | null>(null);
  const [editorNotes, setEditorNotes] = useState('');
  const [editSummary, setEditSummary] = useState('');
  const [editCoreQuestion, setEditCoreQuestion] = useState('');
  const [editShiftHeadline, setEditShiftHeadline] = useState('');
  const [loading, setLoading] = useState(false);
  const [actionMsg, setActionMsg] = useState('');
  const [pipelineStats, setPipelineStats] = useState('');
  const [emailModal, setEmailModal] = useState<EmailModalState>({
    open: false,
    to: '',
    subject: '',
    message: '',
    sending: false,
    error: '',
    success: '',
  });

  const loadList = useCallback(async () => {
    const res = await adminFetch(`${API}?action=list&limit=30`);
    const data = await res.json();
    if (data.success) setReports(data.reports ?? []);
  }, []);

  const loadStats = useCallback(async () => {
    const res = await adminFetch(`${API}?action=stats`);
    const data = await res.json();
    if (data.success && data.pipeline?.by_source_embed) {
      const lines = (data.pipeline.by_source_embed as Array<{ source_api: string; embed_status: string; cnt: number }>)
        .map((r) => `${r.source_api}/${r.embed_status}: ${r.cnt}`);
      setPipelineStats(`${lines.join(' · ')} · 대기: ${data.pipeline.pending ?? 0}`);
    }
  }, []);

  const loadDetail = useCallback(async (id: number) => {
    setLoading(true);
    try {
      const res = await adminFetch(`${API}?action=detail&id=${id}`);
      const data = await res.json();
      if (data.success && data.report) {
        const r = data.report as ReportDetail;
        setDetail(r);
        setSelectedId(id);
        setEditorNotes(r.editor_notes ?? '');
        const scqa = (r.scqa_edited_json ?? r.scqa_raw_json) as ScqaReport | undefined;
        setEditSummary(scqa?.executive_summary ?? r.executive_summary ?? '');
        setEditCoreQuestion(scqa?.core_question ?? '');
        setEditShiftHeadline(scqa?.structural_shift?.headline ?? '');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadList();
    loadStats();
  }, [loadList, loadStats]);

  const postAction = async (body: Record<string, unknown>) => {
    setActionMsg('');
    setLoading(true);
    try {
      const res = await adminFetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!data.success && data.error) {
        setActionMsg(String(data.error));
        return data;
      }
      setActionMsg('완료');
      await loadList();
      await loadStats();
      if (selectedId) await loadDetail(selectedId);
      return data;
    } catch {
      setActionMsg('요청 실패');
      return null;
    } finally {
      setLoading(false);
    }
  };

  const saveReviewed = async () => {
    if (!detail) return;
    const base = structuredClone(detail.scqa_edited_json ?? detail.scqa_raw_json ?? {}) as ScqaReport;
    base.core_question = editCoreQuestion;
    base.executive_summary = editSummary;
    base.structural_shift = {
      ...(base.structural_shift ?? {}),
      headline: editShiftHeadline,
    };
    await postAction({
      action: 'update',
      id: detail.id,
      status: 'reviewed',
      scqa_edited_json: base,
      editor_notes: editorNotes,
      edit_reason: 'admin_ui_review',
    });
  };

  const setStatus = async (status: ReportStatus) => {
    if (!detail) return;
    await postAction({ action: 'update_status', id: detail.id, status, editor_notes: editorNotes });
  };

  const openPdfPreview = () => {
    if (!detail) return;
    window.open(`${API}?action=preview_pdf&id=${detail.id}`, '_blank');
  };

  const downloadPdf = () => {
    if (!detail) return;
    window.location.href = `${API}?action=export_pdf&id=${detail.id}`;
  };

  const openEmailModal = () => {
    if (!detail) return;
    const reportWeek = detail.report_week || '';
    const scqaData = (detail.scqa_edited_json ?? detail.scqa_raw_json) as ScqaReport | undefined;
    const coreQuestion = scqaData?.core_question || scqaData?.structural_shift?.headline || '주간 전략 레포트';
    
    setEmailModal({
      open: true,
      to: '',
      subject: `[the gist] 주간 지정학 전략 레포트 ${reportWeek}`,
      message: `핵심 질문: ${coreQuestion}\n\n첨부된 PDF를 확인해 주세요.`,
      sending: false,
      error: '',
      success: '',
    });
  };

  const closeEmailModal = () => {
    setEmailModal((prev) => ({ ...prev, open: false, error: '', success: '' }));
  };

  const sendEmail = async (e: FormEvent) => {
    e.preventDefault();
    if (!detail || emailModal.sending) return;

    if (!emailModal.to.trim() || !emailModal.to.includes('@')) {
      setEmailModal((prev) => ({ ...prev, error: '유효한 이메일 주소를 입력해주세요' }));
      return;
    }

    setEmailModal((prev) => ({ ...prev, sending: true, error: '', success: '' }));

    try {
      const res = await adminFetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_email',
          id: detail.id,
          to: emailModal.to.trim(),
          subject: emailModal.subject.trim() || undefined,
          message: emailModal.message.trim() || undefined,
        }),
      });
      const data = await res.json();

      if (data.success) {
        setEmailModal((prev) => ({
          ...prev,
          sending: false,
          success: `이메일이 발송되었습니다 (${data.to})`,
        }));
        setTimeout(() => closeEmailModal(), 2000);
      } else {
        setEmailModal((prev) => ({
          ...prev,
          sending: false,
          error: data.error || '발송에 실패했습니다',
        }));
      }
    } catch {
      setEmailModal((prev) => ({
        ...prev,
        sending: false,
        error: '요청 중 오류가 발생했습니다',
      }));
    }
  };

  const scqa = detail ? ((detail.scqa_edited_json ?? detail.scqa_raw_json) as ScqaReport | undefined) : undefined;
  const diffs = Array.isArray(detail?.edit_diff_json) ? detail!.edit_diff_json! : [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold text-white">전략 Intelligence 레포트</h2>
          <p className="text-slate-500 text-sm mt-1">the gist 톤 · 한국어 SCQA · 어드민 전용</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" disabled={loading} onClick={() => postAction({ action: 'collect' })}
            className="px-3 py-2 text-sm rounded-lg bg-slate-700 text-white hover:bg-slate-600 disabled:opacity-50">수집</button>
          <button type="button" disabled={loading} onClick={() => postAction({ action: 'reprocess', limit: 80 })}
            className="px-3 py-2 text-sm rounded-lg bg-indigo-600/80 text-white hover:bg-indigo-600 disabled:opacity-50">NYT/Guardian 재임베딩</button>
          <button type="button" disabled={loading} onClick={() => postAction({ action: 'generate' })}
            className="px-3 py-2 text-sm rounded-lg bg-cyan-600 text-white hover:bg-cyan-500 disabled:opacity-50">레포트 생성</button>
        </div>
      </div>

      {pipelineStats && <p className="text-xs text-slate-500 font-mono bg-slate-800/50 rounded-lg px-3 py-2">{pipelineStats}</p>}
      {actionMsg && <p className="text-sm text-cyan-400">{actionMsg}</p>}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-1 space-y-2 max-h-[70vh] overflow-y-auto">
          {reports.map((r) => (
            <button key={r.id} type="button" onClick={() => loadDetail(r.id)}
              className={`w-full text-left p-4 rounded-xl border transition-colors ${selectedId === r.id ? 'border-cyan-500/50 bg-cyan-500/10' : 'border-slate-700/50 bg-slate-800/30 hover:bg-slate-800/60'}`}>
              <div className="flex items-center gap-2 mb-1">
                <span className="text-white font-medium">{r.report_week}</span>
                <span className={`text-[10px] px-2 py-0.5 rounded-full ${statusStyle[r.status]}`}>{statusLabel[r.status]}</span>
              </div>
              <p className="text-xs text-slate-500">{r.period_start} ~ {r.period_end}</p>
              <p className="text-sm text-slate-400 mt-2 line-clamp-2">{r.executive_summary}</p>
            </button>
          ))}
        </div>

        <div className="lg:col-span-2">
          {!detail && (
            <div className="rounded-xl border border-slate-700/50 bg-slate-800/20 p-8 text-center text-slate-500">레포트를 선택하세요</div>
          )}
          {detail && scqa && (
            <div className="space-y-4">
              <div className="flex flex-wrap gap-2 items-center">
                <span className={`text-xs px-2 py-1 rounded-full ${statusStyle[detail.status]}`}>{statusLabel[detail.status]}</span>
                <span className="text-xs text-slate-500">신뢰도: {detail.confidence}</span>
                {scqa.meta?.language === 'ko' && <span className="text-xs text-emerald-500/80">한국어</span>}
                <div className="ml-auto flex flex-wrap gap-2">
                  {detail.status === 'draft' && (
                    <button type="button" onClick={() => saveReviewed()} disabled={loading}
                      className="px-3 py-1.5 text-xs rounded-lg bg-amber-600 text-white hover:bg-amber-500">검토 저장</button>
                  )}
                  {detail.status === 'reviewed' && (
                    <button type="button" onClick={() => setStatus('approved')} disabled={loading}
                      className="px-3 py-1.5 text-xs rounded-lg bg-emerald-600 text-white hover:bg-emerald-500">승인</button>
                  )}
                  {detail.status !== 'draft' && (
                    <button type="button" onClick={() => setStatus('draft')} disabled={loading}
                      className="px-3 py-1.5 text-xs rounded-lg bg-slate-600 text-white hover:bg-slate-500">초안으로</button>
                  )}
                </div>
              </div>

              {/* PDF 및 이메일 버튼 */}
              <div className="flex flex-wrap gap-2 items-center pt-2 border-t border-slate-700/50">
                <button
                  type="button"
                  onClick={openPdfPreview}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-slate-700 text-white hover:bg-slate-600"
                >
                  <MaterialIcon name="visibility" size={14} className="w-3.5 h-3.5" />
                  PDF 미리보기
                </button>
                <button
                  type="button"
                  onClick={downloadPdf}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-slate-700 text-white hover:bg-slate-600"
                >
                  <MaterialIcon name="download" size={14} className="w-3.5 h-3.5" />
                  PDF 다운로드
                </button>
                <button
                  type="button"
                  onClick={openEmailModal}
                  disabled={detail.status !== 'approved'}
                  className={`flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg ${
                    detail.status === 'approved'
                      ? 'bg-cyan-600 text-white hover:bg-cyan-500'
                      : 'bg-slate-800 text-slate-500 cursor-not-allowed'
                  }`}
                  title={detail.status !== 'approved' ? '승인된 레포트만 이메일 발송 가능' : ''}
                >
                  <MaterialIcon name="mail" size={14} className="w-3.5 h-3.5" />
                  이메일 발송
                </button>
                {detail.status !== 'approved' && (
                  <span className="text-[10px] text-slate-500">승인 후 이메일 발송 가능</span>
                )}
              </div>

              <div className="rounded-xl border border-slate-700/50 bg-slate-800/30 p-4 space-y-3">
                <Field label="핵심 질문" value={editCoreQuestion} onChange={setEditCoreQuestion} />
                <Field label="경영진 요약" value={editSummary} onChange={setEditSummary} multiline rows={4} />
                <Field label="구조적 변화 헤드라인" value={editShiftHeadline} onChange={setEditShiftHeadline} />
                <Field label="편집자 메모" value={editorNotes} onChange={setEditorNotes} multiline rows={2} />
              </div>

              {scqa.structural_shift && (scqa.structural_shift.headline || scqa.structural_shift.from_pattern) && (
                <Section title="구조적 변화 (Structural Shift)" icon="trending_up">
                  {scqa.structural_shift.headline && <p className="text-white font-medium mb-2">{scqa.structural_shift.headline}</p>}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    {scqa.structural_shift.from_pattern && (
                      <div className="bg-slate-900/50 rounded-lg p-3">
                        <p className="text-slate-500 text-xs mb-1">기존 패턴</p>
                        <p className="text-slate-300">{scqa.structural_shift.from_pattern}</p>
                      </div>
                    )}
                    {scqa.structural_shift.to_pattern && (
                      <div className="bg-slate-900/50 rounded-lg p-3">
                        <p className="text-slate-500 text-xs mb-1">새 패턴</p>
                        <p className="text-slate-300">{scqa.structural_shift.to_pattern}</p>
                      </div>
                    )}
                  </div>
                  {scqa.structural_shift.why_now && <p className="text-slate-400 text-sm mt-3">{scqa.structural_shift.why_now}</p>}
                </Section>
              )}

              {scqa.situation?.narrative && (
                <Section title="상황 (Situation)" icon="public">
                  {scqa.situation.narrative.split('\n\n').map((p, i) => (
                    <p key={i} className="text-slate-300 text-sm mb-2">{p}</p>
                  ))}
                  {scqa.situation.timeline?.length ? (
                    <ul className="mt-3 space-y-2 text-sm">
                      {scqa.situation.timeline.map((t, i) => (
                        <li key={i} className="text-slate-400 border-l-2 border-cyan-500/40 pl-3">
                          <span className="text-cyan-400/80">{t.date}</span> — {t.event}
                          <span className="text-slate-600 text-xs ml-1">#{t.source_id}</span>
                        </li>
                      ))}
                    </ul>
                  ) : null}
                </Section>
              )}

              {scqa.complication?.narrative_collisions?.length ? (
                <Section title="관점 충돌 (Narrative Collisions)" icon="compare_arrows">
                  {scqa.complication.narrative_collisions.map((c, i) => (
                    <div key={i} className="mb-3 p-3 rounded-lg bg-slate-900/50 text-sm space-y-1">
                      {c.label && <p className="text-white font-medium">{c.label}</p>}
                      <p className="text-slate-400"><span className="text-cyan-400/80">{c.actor_a}</span>: {c.view_a}</p>
                      <p className="text-slate-400"><span className="text-amber-400/80">{c.actor_b}</span>: {c.view_b}</p>
                      {c.collision && <p className="text-slate-300 mt-2">{c.collision}</p>}
                    </div>
                  ))}
                </Section>
              ) : null}

              {scqa.complication?.perspectives?.length ? (
                <Section title="관점 (Perspectives)" icon="forum">
                  {scqa.complication.perspectives.map((p, i) => (
                    <div key={i} className="mb-3 p-3 rounded-lg bg-slate-900/50 text-sm">
                      <p className="text-slate-300">{p.viewpoint}</p>
                      {p.quote && <p className="text-slate-500 mt-1 italic">&ldquo;{p.quote}&rdquo;</p>}
                    </div>
                  ))}
                </Section>
              ) : null}

              {scqa.answer && (
                <Section title="답변 (Answer)" icon="lightbulb">
                  {scqa.answer.implication && <p className="text-slate-300 text-sm mb-2">{scqa.answer.implication}</p>}
                  {scqa.answer.why_it_matters_chain?.map((step, i) => (
                    <p key={i} className="text-sm text-slate-400">{i + 1}. {step}</p>
                  ))}
                  {scqa.answer.action_matrix && (
                    <div className="grid grid-cols-3 gap-2 mt-3 text-xs">
                      {([['watch', '주시'], ['consider', '검토'], ['act', '대응']] as const).map(([k, label]) => (
                        <div key={k} className="bg-slate-900/50 rounded-lg p-2">
                          <p className="text-slate-500 mb-1">{label}</p>
                          <ul className="text-slate-400 space-y-1">
                            {(scqa.answer!.action_matrix![k] ?? []).map((item, j) => (
                              <li key={j}>• {item}</li>
                            ))}
                          </ul>
                        </div>
                      ))}
                    </div>
                  )}
                </Section>
              )}

              {diffs.length > 0 && (
                <Section title={`편집 이력 (${diffs.length}건)`} icon="history">
                  <ul className="text-xs text-slate-500 space-y-1 max-h-32 overflow-y-auto">
                    {diffs.slice(0, 20).map((d, i) => (
                      <li key={i}><span className="text-cyan-500/70">{d.path}</span> 수정됨</li>
                    ))}
                  </ul>
                </Section>
              )}
            </div>
          )}
        </div>
      </div>

      {/* 이메일 발송 모달 */}
      {emailModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-700">
              <h3 className="text-white font-semibold flex items-center gap-2">
                <MaterialIcon name="mail" size={20} className="text-cyan-400" />
                전략 레포트 이메일 발송
              </h3>
              <button
                type="button"
                onClick={closeEmailModal}
                className="text-slate-400 hover:text-white transition-colors"
              >
                <MaterialIcon name="close" size={20} />
              </button>
            </div>

            <form onSubmit={sendEmail} className="p-5 space-y-4">
              <div>
                <label className="block text-xs text-slate-400 mb-1.5">
                  수신자 이메일 <span className="text-red-400">*</span>
                </label>
                <input
                  type="email"
                  value={emailModal.to}
                  onChange={(e) => setEmailModal((prev) => ({ ...prev, to: e.target.value, error: '' }))}
                  placeholder="recipient@example.com"
                  className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1.5">제목</label>
                <input
                  type="text"
                  value={emailModal.subject}
                  onChange={(e) => setEmailModal((prev) => ({ ...prev, subject: e.target.value }))}
                  className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none"
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1.5">메시지 (선택)</label>
                <textarea
                  value={emailModal.message}
                  onChange={(e) => setEmailModal((prev) => ({ ...prev, message: e.target.value }))}
                  rows={3}
                  className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none resize-none"
                />
              </div>

              <div className="bg-slate-800/50 rounded-lg p-3 text-xs text-slate-400">
                <p className="flex items-center gap-1.5 mb-1">
                  <MaterialIcon name="attach_file" size={14} />
                  PDF 파일이 자동 첨부됩니다
                </p>
                <p className="text-slate-500">
                  {detail?.report_week && `strategic-report-TG-SR-${detail.report_week}.pdf`}
                </p>
              </div>

              {emailModal.error && (
                <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-3 text-sm text-red-400">
                  {emailModal.error}
                </div>
              )}

              {emailModal.success && (
                <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-lg p-3 text-sm text-emerald-400 flex items-center gap-2">
                  <MaterialIcon name="check_circle" size={16} />
                  {emailModal.success}
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={closeEmailModal}
                  className="flex-1 px-4 py-2.5 text-sm rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800 transition-colors"
                >
                  취소
                </button>
                <button
                  type="submit"
                  disabled={emailModal.sending}
                  className="flex-1 px-4 py-2.5 text-sm rounded-lg bg-cyan-600 text-white hover:bg-cyan-500 disabled:bg-cyan-600/50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2"
                >
                  {emailModal.sending ? (
                    <>
                      <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                      발송 중...
                    </>
                  ) : (
                    <>
                      <MaterialIcon name="send" size={16} />
                      발송
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

function Field({ label, value, onChange, multiline, rows = 2 }: {
  label: string; value: string; onChange: (v: string) => void; multiline?: boolean; rows?: number;
}) {
  const cls = 'w-full bg-slate-900/80 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white';
  return (
    <div>
      <label className="block text-xs text-slate-500 mb-1">{label}</label>
      {multiline ? (
        <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={rows} className={cls} />
      ) : (
        <input value={value} onChange={(e) => onChange(e.target.value)} className={cls} />
      )}
    </div>
  );
}

function Section({ title, icon, children }: { title: string; icon: string; children: ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-700/50 bg-slate-800/30 p-4">
      <h3 className="flex items-center gap-2 text-sm font-semibold text-white mb-3">
        <MaterialIcon name={icon} className="w-4 h-4 text-cyan-400" size={16} />
        {title}
      </h3>
      {children}
    </div>
  );
}

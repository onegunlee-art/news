import React, { useMemo, useRef, useState } from 'react';
import { api } from '../../services/api';
import {
  downloadCorporateEmailTemplate,
  parseCorporateEmailsFromSpreadsheet,
} from '../../utils/parseCorporateEmailsFromSpreadsheet';

export type CorporateBatchResult = {
  created: string[];
  updated: string[];
  skipped: string[];
  errors: Array<{ email: string; message: string }>;
  emails_sent: number;
  summary: {
    created_count: number;
    updated_count: number;
    skipped_count: number;
    error_count: number;
  };
};

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const COMPANY_OPTIONS = [
  { value: 'hyundai', label: '현대자동차' },
  { value: 'samsung', label: '삼성' },
  { value: 'other', label: '기타' },
] as const;

function parseEmails(text: string): string[] {
  const seen = new Set<string>();
  const result: string[] = [];
  text.split(/[\n,;]+/).forEach((part) => {
    const email = part.trim().toLowerCase();
    if (!email.includes('@')) return;
    if (seen.has(email)) return;
    seen.add(email);
    result.push(email);
  });
  return result;
}

const CorporateRegistrationModal: React.FC<Props> = ({ isOpen, onClose, onSuccess }) => {
  const [inputMode, setInputMode] = useState<'text' | 'excel'>('text');
  const [emails, setEmails] = useState('');
  const [uploadedFileName, setUploadedFileName] = useState('');
  const [isParsingFile, setIsParsingFile] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [password, setPassword] = useState('gist2026');
  const [companyTag, setCompanyTag] = useState<string>('hyundai');
  const [customCompanyName, setCustomCompanyName] = useState('');
  const [subscriptionMonths, setSubscriptionMonths] = useState(12);
  const [sendWelcomeEmail, setSendWelcomeEmail] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<CorporateBatchResult | null>(null);

  const emailList = useMemo(() => parseEmails(emails), [emails]);

  if (!isOpen) return null;

  const resetForm = () => {
    setInputMode('text');
    setEmails('');
    setUploadedFileName('');
    setPassword('gist2026');
    setCompanyTag('hyundai');
    setCustomCompanyName('');
    setSubscriptionMonths(12);
    setSendWelcomeEmail(true);
    setError('');
    setResult(null);
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    setError('');
    setResult(null);
    setIsParsingFile(true);
    try {
      const parsed = await parseCorporateEmailsFromSpreadsheet(file);
      setEmails(parsed.join('\n'));
      setUploadedFileName(file.name);
      setInputMode('excel');
    } catch (err: unknown) {
      setUploadedFileName('');
      setError(err instanceof Error ? err.message : '파일을 읽지 못했습니다.');
    } finally {
      setIsParsingFile(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setResult(null);

    if (emailList.length === 0) {
      setError('유효한 이메일을 1개 이상 입력해주세요.');
      return;
    }
    if (password.length < 6) {
      setError('비밀번호는 6자 이상이어야 합니다.');
      return;
    }
    if (companyTag === 'other' && !customCompanyName.trim()) {
      setError('기타 선택 시 회사 표시명을 입력해주세요.');
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await api.post<{
        success: boolean;
        message?: string;
        data?: CorporateBatchResult;
      }>('/admin/users/corporate-batch', {
        emails: emailList,
        password,
        company_tag: companyTag,
        company_display_name: companyTag === 'other' ? customCompanyName.trim() : undefined,
        subscription_months: subscriptionMonths,
        send_welcome_email: sendWelcomeEmail,
      });

      if (res.data.success && res.data.data) {
        setResult(res.data.data);
        onSuccess();
      } else {
        setError(res.data.message || '등록에 실패했습니다.');
      }
    } catch (err: unknown) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
          : undefined;
      setError(message || '등록 중 오류가 발생했습니다.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
      onClick={handleClose}
    >
      <div
        className="bg-slate-800 rounded-2xl p-6 border border-slate-700 w-full max-w-xl max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-lg font-semibold text-white mb-1">기업 고객 일괄 등록</h3>
        <p className="text-sm text-slate-400 mb-4">
          이메일·공통 비밀번호로 계정을 만들고, 구독과 OTP 생략을 자동 설정합니다.
        </p>

        {error && (
          <div className="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-300 text-sm">
            {error}
          </div>
        )}

        {result && (
          <div className="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-200 text-sm space-y-1">
            <p>
              신규 {result.summary.created_count}명 · 갱신 {result.summary.updated_count}명 ·
              건너뜀 {result.summary.skipped_count}명
              {result.summary.error_count > 0 && ` · 오류 ${result.summary.error_count}건`}
            </p>
            {sendWelcomeEmail && <p>안내 메일 발송: {result.emails_sent}건</p>}
            {result.errors.length > 0 && (
              <ul className="mt-2 text-red-300 text-xs list-disc pl-4">
                {result.errors.slice(0, 5).map((item) => (
                  <li key={item.email}>
                    {item.email}: {item.message}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-slate-400 text-xs mb-1">회사</label>
            <select
              value={companyTag}
              onChange={(e) => setCompanyTag(e.target.value)}
              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"
            >
              {COMPANY_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          {companyTag === 'other' && (
            <div>
              <label className="block text-slate-400 text-xs mb-1">회사 표시명 (안내 메일용)</label>
              <input
                type="text"
                value={customCompanyName}
                onChange={(e) => setCustomCompanyName(e.target.value)}
                placeholder="예: ABC 그룹"
                className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"
              />
            </div>
          )}

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="block text-slate-400 text-xs">이메일 목록</label>
              <button
                type="button"
                onClick={downloadCorporateEmailTemplate}
                className="text-xs text-cyan-400 hover:text-cyan-300"
              >
                Excel 템플릿 다운로드 (.csv)
              </button>
            </div>

            <div className="flex gap-1 mb-2 p-1 bg-slate-900/50 rounded-lg border border-slate-700">
              <button
                type="button"
                onClick={() => setInputMode('text')}
                className={`flex-1 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  inputMode === 'text'
                    ? 'bg-slate-700 text-white'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                직접 입력
              </button>
              <button
                type="button"
                onClick={() => {
                  setInputMode('excel');
                  fileInputRef.current?.click();
                }}
                className={`flex-1 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  inputMode === 'excel'
                    ? 'bg-slate-700 text-white'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                Excel 업로드
              </button>
            </div>

            <input
              ref={fileInputRef}
              type="file"
              accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              className="hidden"
              onChange={handleFileChange}
            />

            {inputMode === 'excel' && (
              <div
                className="mb-2 border border-dashed border-slate-600 rounded-lg p-4 text-center cursor-pointer hover:border-cyan-500/50 hover:bg-slate-900/30 transition-colors"
                onClick={() => !isParsingFile && fileInputRef.current?.click()}
              >
                {isParsingFile ? (
                  <p className="text-sm text-slate-400">파일 읽는 중...</p>
                ) : uploadedFileName ? (
                  <>
                    <p className="text-sm text-emerald-300 font-medium">{uploadedFileName}</p>
                    <p className="text-xs text-slate-500 mt-1">클릭하여 다른 파일 선택</p>
                  </>
                ) : (
                  <>
                    <p className="text-sm text-slate-300">xlsx, xls, csv 파일을 선택하세요</p>
                    <p className="text-xs text-slate-500 mt-1">첫 시트 · email(이메일) 열 또는 이메일 형식 값</p>
                  </>
                )}
              </div>
            )}

            <textarea
              value={emails}
              onChange={(e) => {
                setEmails(e.target.value);
                if (uploadedFileName) setUploadedFileName('');
              }}
              rows={inputMode === 'excel' ? 6 : 8}
              placeholder={
                inputMode === 'excel'
                  ? '업로드한 이메일이 여기 표시됩니다. 수정 가능합니다.'
                  : 'user1@hyundai.com\nuser2@hyundai.com'
              }
              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm font-mono"
            />
            <p className="text-xs text-slate-500 mt-1">
              {emailList.length}명 감지됨
              {inputMode === 'excel' && uploadedFileName ? ` · ${uploadedFileName}` : ' · 줄바꿈·쉼표 구분'}
            </p>
          </div>

          <div>
            <label className="block text-slate-400 text-xs mb-1">공통 비밀번호</label>
            <input
              type="text"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="off"
              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"
            />
          </div>

          <div>
            <label className="block text-slate-400 text-xs mb-1">구독 기간</label>
            <select
              value={subscriptionMonths}
              onChange={(e) => setSubscriptionMonths(Number(e.target.value))}
              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"
            >
              <option value={6}>6개월</option>
              <option value={12}>12개월</option>
              <option value={24}>24개월</option>
            </select>
          </div>

          <label className="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input
              type="checkbox"
              checked={sendWelcomeEmail}
              onChange={(e) => setSendWelcomeEmail(e.target.checked)}
              className="rounded border-slate-600"
            />
            등록 완료 안내 이메일 발송
          </label>

          <div className="flex gap-2 pt-2">
            <button
              type="button"
              onClick={handleClose}
              className="flex-1 py-2.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700/50 text-sm"
            >
              닫기
            </button>
            <button
              type="submit"
              disabled={isSubmitting || emailList.length === 0}
              className="flex-1 py-2.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-medium text-sm disabled:opacity-50"
            >
              {isSubmitting ? '등록 중...' : '등록하기'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CorporateRegistrationModal;

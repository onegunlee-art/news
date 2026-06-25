import * as XLSX from 'xlsx';

const EMAIL_HEADER_KEYS = ['email', 'e-mail', 'e_mail', 'mail', '이메일', '전자우편', '아이디'];

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function normalizeEmail(value: unknown): string | null {
  if (value == null) return null;
  const email = String(value).trim().toLowerCase();
  if (!EMAIL_REGEX.test(email)) return null;
  return email;
}

function headerLooksLikeEmail(key: string): boolean {
  const norm = key.trim().toLowerCase().replace(/[\s_-]/g, '');
  return EMAIL_HEADER_KEYS.some((h) => norm.includes(h.replace(/[\s_-]/g, '')));
}

function extractEmailFromRow(row: Record<string, unknown>): string | null {
  for (const [key, value] of Object.entries(row)) {
    if (headerLooksLikeEmail(key)) {
      const email = normalizeEmail(value);
      if (email) return email;
    }
  }
  for (const value of Object.values(row)) {
    const email = normalizeEmail(value);
    if (email) return email;
  }
  return null;
}

function dedupeEmails(list: string[]): string[] {
  const seen = new Set<string>();
  const result: string[] = [];
  for (const email of list) {
    if (seen.has(email)) continue;
    seen.add(email);
    result.push(email);
  }
  return result;
}

/**
 * Excel(.xlsx/.xls) 또는 CSV에서 이메일 목록 추출.
 * - 'email' / '이메일' 헤더 열 우선
 * - 없으면 행에서 이메일 형식 값 탐색
 * - 단일 열(헤더 없음)도 지원
 */
export async function parseCorporateEmailsFromSpreadsheet(file: File): Promise<string[]> {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (!['xlsx', 'xls', 'csv'].includes(ext)) {
    throw new Error('xlsx, xls, csv 파일만 업로드할 수 있습니다.');
  }

  const buffer = await file.arrayBuffer();
  const workbook = XLSX.read(buffer, { type: 'array' });
  const sheetName = workbook.SheetNames[0];
  if (!sheetName) {
    throw new Error('시트가 비어 있습니다.');
  }

  const sheet = workbook.Sheets[sheetName];
  const rows = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet, { defval: '' });
  const collected: string[] = [];

  for (const row of rows) {
    const email = extractEmailFromRow(row);
    if (email) collected.push(email);
  }

  if (collected.length === 0) {
    const matrix = XLSX.utils.sheet_to_json<unknown[]>(sheet, { header: 1, defval: '' });
    for (const row of matrix) {
      if (!Array.isArray(row)) continue;
      for (const cell of row) {
        const email = normalizeEmail(cell);
        if (email) {
          collected.push(email);
          break;
        }
      }
    }
  }

  const unique = dedupeEmails(collected);
  if (unique.length === 0) {
    throw new Error('파일에서 유효한 이메일을 찾지 못했습니다. email(이메일) 열을 확인해주세요.');
  }
  if (unique.length > 100) {
    throw new Error(`이메일이 ${unique.length}개입니다. 한 번에 최대 100명까지 등록할 수 있습니다.`);
  }

  return unique;
}

export function downloadCorporateEmailTemplate(): void {
  const csv = 'email\nuser1@company.com\nuser2@company.com\n';
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'gist_corporate_emails_template.csv';
  a.click();
  URL.revokeObjectURL(url);
}

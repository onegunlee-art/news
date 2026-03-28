export const ADMIN_DRAFT_AUTOSAVE_PREFIX = 'admin_draft_autosave_'
export const AUTOSAVE_SCHEMA_VERSION = 1

/** AdminDraftPreviewEdit state와 동일 스키마 (순환 참조 방지용) */
export interface DraftAutosavePayload {
  news: {
    id: number
    title: string
    description?: string | null
    content: string | null
    why_important: string | null
    narration: string | null
    future_prediction?: string | null
    source: string | null
    source_url?: string | null
    original_source?: string | null
    original_title?: string | null
    url: string
    published_at: string | null
    created_at?: string | null
    updated_at?: string | null
    image_url?: string | null
    author?: string | null
    category?: string | null
    category_parent?: string | null
    status?: string
  }
  categoryParent: string
  categorySub: string
  categorySubCustom: string
  dallePrompt: string
}

export interface DraftAutosaveRecord {
  v: number
  savedAt: number
  /** 서버에서 이 초안을 마지막으로 불러온 시점의 updated_at (비교용) */
  serverUpdatedAtWhenLoaded: string | null
  payload: DraftAutosavePayload
}

export function draftAutosaveKey(draftId: number): string {
  return `${ADMIN_DRAFT_AUTOSAVE_PREFIX}${draftId}`
}

export function parseServerUpdatedAtMs(isoOrSql: string | null | undefined): number {
  if (!isoOrSql) return 0
  const s = String(isoOrSql).trim()
  const normalized = s.includes('T') ? s : s.replace(/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})/, '$1T$2')
  const t = Date.parse(normalized)
  return Number.isFinite(t) ? t : 0
}

/** 복구 제안 여부: 로컬이 서버 수정 시각보다 최신이고 내용이 다름 */
export function shouldOfferLocalRestore(
  local: DraftAutosaveRecord | null,
  serverUpdatedAt: string | null | undefined,
  serverSnapshotJson: string,
  localPayloadJson: string
): boolean {
  if (!local || local.v !== AUTOSAVE_SCHEMA_VERSION) return false
  const serverMs = parseServerUpdatedAtMs(serverUpdatedAt)
  if (local.savedAt <= serverMs) return false
  return localPayloadJson !== serverSnapshotJson
}

export function loadDraftAutosave(draftId: number): DraftAutosaveRecord | null {
  if (typeof localStorage === 'undefined') return null
  try {
    const raw = localStorage.getItem(draftAutosaveKey(draftId))
    if (!raw) return null
    const data = JSON.parse(raw) as DraftAutosaveRecord
    if (!data || data.v !== AUTOSAVE_SCHEMA_VERSION || !data.payload?.news) return null
    return data
  } catch {
    return null
  }
}

export function saveDraftAutosave(record: DraftAutosaveRecord, draftId: number): void {
  if (typeof localStorage === 'undefined') return
  localStorage.setItem(draftAutosaveKey(draftId), JSON.stringify(record))
}

export function clearDraftAutosave(draftId: number): void {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.removeItem(draftAutosaveKey(draftId))
  } catch {
    /* ignore */
  }
}

export function serializeDraftPayload(p: DraftAutosavePayload): string {
  return JSON.stringify({
    n: p.news,
    cp: p.categoryParent,
    cs: p.categorySub,
    csc: p.categorySubCustom,
    dp: p.dallePrompt,
  })
}

export function isQuotaExceededError(e: unknown): boolean {
  return (
    (e instanceof DOMException && e.name === 'QuotaExceededError') ||
    (typeof e === 'object' &&
      e !== null &&
      'name' in e &&
      (e as DOMException).name === 'QuotaExceededError')
  )
}

import type { EduThoughtBoardSlot } from '../services/eduApi'

export type EssayAssemblePiece = {
  layerId: string
  index: number
  label: string
  heading: string
  role: string
  scqaKey: string
  fullText: string
  displayText: string
  connector: string
}

export const ASSEMBLE_EMPTY_PLACEHOLDER = '생각판 내용을 불러오는 중…'

const CONNECTOR_BY_INDEX: Record<number, string> = {
  1: '',
  2: '왜냐하면 ',
  3: '그런데 ',
  4: '한편 ',
  5: '그래서 ',
  6: '따라서 ',
}

/** 애니 칩 표시용 — compose/API에는 fullText 사용 */
export function truncatePieceDisplay(text: string, max = 72): string {
  const trimmed = text.trim()
  if (trimmed.length <= max) return trimmed

  const sentenceEnd = trimmed.search(/[.!?…]\s/)
  if (sentenceEnd > 0 && sentenceEnd <= max) {
    return trimmed.slice(0, sentenceEnd + 1).trim()
  }

  return `${trimmed.slice(0, max).trim()}…`
}

/** 구조 기반 연결어 — slot.text 내용 참조 없음 */
export function connectorForSlot(slot: Pick<EduThoughtBoardSlot, 'index' | 'role' | 'scqa_key'>): string {
  const idx = slot.index
  if (idx >= 1 && idx <= 6 && CONNECTOR_BY_INDEX[idx] !== undefined) {
    return CONNECTOR_BY_INDEX[idx]
  }

  const role = (slot.role ?? '').trim()
  if (role === 'stance') return ''
  if (role === 'reason') return '왜냐하면 '
  if (role === 'qualification') return '그런데 '
  if (role === 'counter') return '한편 '
  if (role === 'refined') return '그래서 '
  if (role === 'conclusion') return '따라서 '

  const key = (slot.scqa_key ?? '').trim()
  if (key === 'S') return ''
  if (key === 'C') return '왜냐하면 '
  if (key === 'Q') return '한편 '
  if (key === 'A') return '그래서 '
  if (key === 'conclusion') return '따라서 '

  return ''
}

/** filled slot만 index 순 — source of truth: slot.text */
export function piecesFromThoughtBoard(board: EduThoughtBoardSlot[]): EssayAssemblePiece[] {
  return [...board]
    .filter(slot => slot.filled && slot.text.trim() !== '')
    .sort((a, b) => a.index - b.index)
    .map(slot => {
      const fullText = slot.text.trim()
      return {
        layerId: slot.layer_id,
        index: slot.index,
        label: slot.label,
        heading: slot.heading,
        role: slot.role ?? '',
        scqaKey: slot.scqa_key,
        fullText,
        displayText: truncatePieceDisplay(fullText),
        connector: connectorForSlot(slot),
      }
    })
}

/** 타이핑 연출용 초안 — compose API 입력 아님 */
export function assembleDraftFromBoard(board: EduThoughtBoardSlot[]): string {
  const pieces = piecesFromThoughtBoard(board)
  if (pieces.length === 0) return ASSEMBLE_EMPTY_PLACEHOLDER

  return pieces
    .map(p => `${p.connector}${p.fullText}`)
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim()
}

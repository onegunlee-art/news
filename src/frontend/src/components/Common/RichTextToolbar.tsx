import { RefObject } from 'react'

const HIGHLIGHT_COLORS = [
  { name: '노랑', bg: '#fef08a' },
  { name: '민트', bg: '#bbf7d0' },
  { name: '하늘', bg: '#bae6fd' },
] as const

interface RichTextToolbarProps {
  /** textarea 또는 contenteditable div ref */
  editableRef?: RefObject<HTMLTextAreaElement | HTMLDivElement | null>
  /** @deprecated use editableRef */
  textareaRef?: RefObject<HTMLTextAreaElement | null>
  value: string
  onChange: (value: string) => void
  disabled?: boolean
}

function wrapSelection(
  value: string,
  start: number,
  end: number,
  before: string,
  after: string
): { newValue: string; newCursorStart: number; newCursorEnd: number } {
  if (start === end) return { newValue: value, newCursorStart: start, newCursorEnd: end }
  const newValue = value.slice(0, start) + before + value.slice(start, end) + after + value.slice(end)
  const newCursorStart = start + before.length
  const newCursorEnd = end + before.length
  return { newValue, newCursorStart, newCursorEnd }
}

export default function RichTextToolbar({
  editableRef,
  textareaRef,
  value,
  onChange,
  disabled,
}: RichTextToolbarProps) {
  const ref = editableRef ?? textareaRef

  const applyWrap = (before: string, after: string) => {
    const el = ref?.current
    if (!el || disabled) return

    if (el instanceof HTMLTextAreaElement) {
      const start = el.selectionStart
      const end = el.selectionEnd
      const { newValue, newCursorStart, newCursorEnd } = wrapSelection(value, start, end, before, after)
      onChange(newValue)
      el.focus()
      requestAnimationFrame(() => {
        el.setSelectionRange(newCursorStart, newCursorEnd)
      })
      return
    }

    // contenteditable div
    const sel = window.getSelection()
    const range = sel?.rangeCount ? sel.getRangeAt(0) : null
    if (!range || !el.contains(range.commonAncestorContainer)) {
      el.focus()
      return
    }
    if (range.collapsed) return

    const fragment = range.extractContents()
    const div = document.createElement('div')
    div.appendChild(fragment)
    const selectedHtml = div.innerHTML
    const wrappedHtml = before + selectedHtml + after
    document.execCommand('insertHTML', false, wrappedHtml)
    el.focus()
    onChange(el.innerHTML)
  }

  return (
    <div className="flex items-center gap-1 flex-wrap mb-1">
      <button
        type="button"
        onClick={() => applyWrap('<b>', '</b>')}
        disabled={disabled}
        className="px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="볼드"
      >
        B
      </button>
      {HIGHLIGHT_COLORS.map(({ name, bg }) => (
        <button
          key={bg}
          type="button"
          onClick={() => applyWrap(`<mark style="background:${bg}">`, '</mark>')}
          disabled={disabled}
          className="w-7 h-7 rounded border border-slate-600 hover:ring-2 hover:ring-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed transition"
          style={{ backgroundColor: bg }}
          title={`하이라이트: ${name}`}
        />
      ))}
    </div>
  )
}

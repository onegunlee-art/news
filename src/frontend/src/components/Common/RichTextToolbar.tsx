import { RefObject } from 'react'

/** 하이라이트: 원래색(제거) | 노랑 | 오렌지 | 퍼플 */
const HIGHLIGHT_OPTIONS = [
  { id: 'none', name: '원래색', bg: 'transparent', isRemove: true },
  { name: '노랑', bg: '#fef08a' },
  { name: '오렌지', bg: '#fed7aa' },
  { name: '퍼플', bg: '#e9d5ff' },
] as const

interface RichTextToolbarProps {
  editableRef?: RefObject<HTMLTextAreaElement | HTMLDivElement | null>
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

  const execCommandAndSync = (cmd: string, valueArg?: string) => {
    const el = ref?.current
    if (!el || disabled) return
    if (el instanceof HTMLTextAreaElement) return
    el.focus()
    document.execCommand(cmd, false, valueArg ?? undefined)
    onChange(el.innerHTML)
  }

  const applyWrap = (before: string, after: string, expandToFormattingTags = true) => {
    const el = ref?.current
    if (!el || disabled) return

    if (el instanceof HTMLTextAreaElement) {
      const start = el.selectionStart
      const end = el.selectionEnd
      const { newValue, newCursorStart, newCursorEnd } = wrapSelection(value, start, end, before, after)
      onChange(newValue)
      el.focus()
      requestAnimationFrame(() => el.setSelectionRange(newCursorStart, newCursorEnd))
      return
    }

    const sel = window.getSelection()
    const range = sel?.rangeCount ? sel.getRangeAt(0) : null
    if (!range || !el.contains(range.commonAncestorContainer)) {
      el.focus()
      return
    }
    if (range.collapsed) return

    // 볼드(B/STRONG)만 선택 영역을 부모 포맷 태그 전체로 확장. 하이라이트는 드래그한 영역만 적용
    if (expandToFormattingTags) {
      const FORMATTING_TAGS = ['B', 'STRONG']
      let node: Node | null = range.commonAncestorContainer
      if (node.nodeType === Node.TEXT_NODE) node = node.parentElement
      if (node?.nodeType === Node.ELEMENT_NODE) {
        let elem: HTMLElement | null = node as HTMLElement
        while (elem && elem !== el) {
          if (FORMATTING_TAGS.includes(elem.tagName) && el.contains(elem)) {
            range.setStartBefore(elem)
            range.setEndAfter(elem)
            break
          }
          elem = elem.parentElement
        }
      }
    }

    const fragment = range.extractContents()
    const div = document.createElement('div')
    div.appendChild(fragment)
    const selectedHtml = div.innerHTML
    const wrappedHtml = before + selectedHtml + after
    document.execCommand('insertHTML', false, wrappedHtml)
    el.focus()
    onChange(el.innerHTML)
  }

  const removeHighlight = () => {
    const el = ref?.current
    if (!el || disabled || el instanceof HTMLTextAreaElement) return

    const sel = window.getSelection()
    const range = sel?.rangeCount ? sel.getRangeAt(0) : null
    if (!range || !el.contains(range.commonAncestorContainer)) {
      el.focus()
      return
    }

    const unwrapElement = (elem: HTMLElement) => {
      const parent = elem.parentElement
      if (!parent) return
      while (elem.firstChild) parent.insertBefore(elem.firstChild, elem)
      parent.removeChild(elem)
      onChange(el.innerHTML)
    }

    const findAndUnwrapAncestor = (tagName: string, checkStyle?: (el: HTMLElement) => boolean) => {
      let node: Node | null = range.commonAncestorContainer
      if (node.nodeType === Node.TEXT_NODE) node = node.parentElement
      if (!node) return false
      let elem: HTMLElement | null = node as HTMLElement
      while (elem && elem !== el) {
        if (elem.tagName === tagName && el.contains(elem) && (!checkStyle || checkStyle(elem))) {
          unwrapElement(elem)
          return true
        }
        elem = elem.parentElement
      }
      return false
    }

    /** 선택 영역과 겹치는 모든 MARK/SPAN(배경) 요소를 찾아 제거 */
    const unwrapAllInRange = (tagName: string, checkStyle?: (el: HTMLElement) => boolean) => {
      const toUnwrap: HTMLElement[] = []
      const walker = document.createTreeWalker(el, NodeFilter.SHOW_ELEMENT, null)
      let node: Node | null
      while ((node = walker.nextNode())) {
        if (node.nodeType !== Node.ELEMENT_NODE) continue
        const elem = node as HTMLElement
        if (elem.tagName !== tagName || !el.contains(elem)) continue
        if (checkStyle && !checkStyle(elem)) continue
        if (range.intersectsNode(elem)) toUnwrap.push(elem)
      }
      toUnwrap.forEach(unwrapElement)
      return toUnwrap.length > 0
    }

    if (range.collapsed) {
      let node: Node | null = range.commonAncestorContainer
      if (node.nodeType === Node.TEXT_NODE) node = node.parentElement
      if (node?.nodeType === Node.ELEMENT_NODE) {
        let elem: HTMLElement | null = node as HTMLElement
        while (elem && elem !== el) {
          if (elem.tagName === 'MARK') {
            range.selectNodeContents(elem)
            findAndUnwrapAncestor('MARK')
            el.focus()
            return
          }
          if (elem.tagName === 'SPAN' && elem.style?.background) {
            range.selectNodeContents(elem)
            findAndUnwrapAncestor('SPAN', (e) => !!e.style?.background)
            el.focus()
            return
          }
          elem = elem.parentElement
        }
      }
    } else {
      if (unwrapAllInRange('MARK')) {
        el.focus()
        return
      }
      if (unwrapAllInRange('SPAN', (e) => !!e.style?.background)) {
        el.focus()
        return
      }
    }
    el.focus()
  }

  const handleHighlight = (opt: (typeof HIGHLIGHT_OPTIONS)[number]) => {
    if ('isRemove' in opt && opt.isRemove) {
      removeHighlight()
      return
    }
    applyWrap(`<mark style="background:${opt.bg}">`, '</mark>', false)
  }

  const insertImage = () => {
    const el = ref?.current
    if (!el || disabled || el instanceof HTMLTextAreaElement) return
    const url = window.prompt('이미지 URL을 입력하세요:', '')
    if (!url || !url.trim()) return
    const safeUrl = url.trim().replace(/[<>"']/g, '')
    el.focus()
    document.execCommand('insertHTML', false, `<img src="${safeUrl}" alt="" style="max-width:100%;height:auto;display:block;margin:0.5rem 0;border-radius:0.25rem;" /><br/>`)
    onChange(el.innerHTML)
  }

  return (
    <div className="flex items-center gap-1 flex-wrap mb-1">
      {/* B I U S - execCommand로 하이라이트와 충돌 없이 중첩 */}
      <button
        type="button"
        onClick={() => execCommandAndSync('bold')}
        disabled={disabled}
        className="px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="볼드"
      >
        B
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('italic')}
        disabled={disabled}
        className="px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm italic disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="이탤릭"
      >
        I
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('underline')}
        disabled={disabled}
        className="px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm underline disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="밑줄"
      >
        U
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('strikeThrough')}
        disabled={disabled}
        className="px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm line-through disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="취소선"
      >
        S
      </button>

      <span className="w-px h-5 bg-slate-600 mx-0.5" aria-hidden />

      {/* 하이라이트 — onMouseDown preventDefault로 클릭 시 선택 영역 유지 */}
      {HIGHLIGHT_OPTIONS.map((opt) =>
        'isRemove' in opt && opt.isRemove ? (
          <button
            key="none"
            type="button"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => handleHighlight(opt)}
            disabled={disabled}
            className="flex items-center gap-1 px-2 py-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs disabled:opacity-50 disabled:cursor-not-allowed transition border border-slate-600"
            title="하이라이트 제거"
          >
            <span className="w-3 h-3 rounded border border-slate-500 bg-transparent" />
            원래색
          </button>
        ) : (
          <button
            key={opt.bg}
            type="button"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => handleHighlight(opt)}
            disabled={disabled}
            className="w-7 h-7 rounded border border-slate-600 hover:ring-2 hover:ring-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed transition"
            style={{ backgroundColor: opt.bg }}
            title={`하이라이트: ${opt.name}`}
          />
        )
      )}

      <span className="w-px h-5 bg-slate-600 mx-0.5" aria-hidden />

      {/* 글자 크기 */}
      <select
        onChange={(e) => {
          const v = e.target.value
          if (v) execCommandAndSync('fontSize', v)
          e.target.value = ''
        }}
        disabled={disabled}
        className="h-8 px-2 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs disabled:opacity-50 disabled:cursor-not-allowed transition border-0 cursor-pointer"
        title="글자 크기"
      >
        <option value="">크기</option>
        <option value="1">작게</option>
        <option value="2">보통</option>
        <option value="3">조금 크게</option>
        <option value="4">크게</option>
        <option value="5">더 크게</option>
      </select>

      <span className="w-px h-5 bg-slate-600 mx-0.5" aria-hidden />

      {/* 정렬: 왼쪽 | 가운데 | 오른쪽 | 양쪽 */}
      <button
        type="button"
        onClick={() => execCommandAndSync('justifyLeft')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="왼쪽 정렬"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 4h16v1.5H2V4zm0 5h12v1.5H2V9zm0 5h16v1.5H2V14z" />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('justifyCenter')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="가운데 정렬"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 4h16v1.5H2V4zm2 5h12v1.5H4V9zm-2 5h16v1.5H2V14z" />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('justifyRight')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="오른쪽 정렬"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 4h16v1.5H2V4zm6 5h12v1.5H8V9zm-6 5h16v1.5H2V14z" />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('justifyFull')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="양쪽 정렬"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 4h16v1.5H2V4zm0 5h16v1.5H2V9zm0 5h16v1.5H2V14z" />
        </svg>
      </button>

      <span className="w-px h-5 bg-slate-600 mx-0.5" aria-hidden />

      {/* 불릿 / 번호 */}
      <button
        type="button"
        onClick={() => execCommandAndSync('insertUnorderedList')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="불릿 포인트"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M4 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
          <path d="M8 5h10v1.5H8V5zm0 5h10v1.5H8V10zm0 5h10v1.5H8V15z" fill="currentColor" />
        </svg>
      </button>
      <button
        type="button"
        onClick={() => execCommandAndSync('insertOrderedList')}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="번호 매기기"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2 3h2v1H2V3zm0 4h2v1H2V7zm0 4h2v1H2v-1zm2 4h12v1.5H4V15zm0-5h12v1.5H4V10zm0-5h12v1.5H4V5z" />
        </svg>
      </button>

      {/* 표 삽입 */}
      <button
        type="button"
        onClick={() => {
          const el = ref?.current
          if (!el || disabled || el instanceof HTMLTextAreaElement) return
          el.focus()
          const tableHtml = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%;"><tbody><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><br/>'
          document.execCommand('insertHTML', false, tableHtml)
          onChange(el.innerHTML)
        }}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="표 삽입 (3x3)"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M3 3h14v14H3V3zm2 2v4h4V5H5zm6 0v4h4V5h-4zm-6 6v4h4v-4H5zm6 0v4h4v-4h-4z" />
        </svg>
      </button>
      {/* 이미지 삽입 */}
      <button
        type="button"
        onClick={insertImage}
        disabled={disabled}
        className="p-1.5 rounded-md bg-slate-700 hover:bg-slate-600 text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed transition"
        title="이미지 삽입"
      >
        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fillRule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clipRule="evenodd" />
        </svg>
      </button>
    </div>
  )
}

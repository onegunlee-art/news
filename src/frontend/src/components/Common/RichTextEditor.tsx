import { useRef, useEffect, useCallback } from 'react'
import RichTextToolbar from './RichTextToolbar'

interface RichTextEditorProps {
  value: string
  onChange: (value: string) => void
  sanitizePaste?: (plainText: string) => string
  placeholder?: string
  rows?: number
  className?: string
  disabled?: boolean
}

/**
 * WYSIWYG 리치 텍스트 에디터 (볼드, 하이라이트가 실제 스타일로 표시됨)
 * contenteditable div 사용 - Enter 시 <br> 삽입 (div 생성 방지)
 */
export default function RichTextEditor({
  value,
  onChange,
  sanitizePaste,
  placeholder = '',
  rows = 6,
  className = '',
  disabled = false,
}: RichTextEditorProps) {
  const divRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const el = divRef.current
    if (!el || document.activeElement === el) return
    const html = value || ''
    if (el.innerHTML !== html) {
      el.innerHTML = html
    }
  }, [value])

  const handleInput = useCallback(() => {
    const el = divRef.current
    if (!el) return
    onChange(el.innerHTML)
  }, [onChange])

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key !== 'Enter') return
      e.preventDefault()
      document.execCommand('insertLineBreak')
      const el = divRef.current
      if (el) onChange(el.innerHTML)
    },
    [onChange]
  )

  const handlePaste = useCallback(
    (e: React.ClipboardEvent) => {
      e.preventDefault()
      const pastedText = e.clipboardData.getData('text/plain')
      if (sanitizePaste) {
        const html = sanitizePaste(pastedText)
        document.execCommand('insertHTML', false, html)
      } else {
        document.execCommand('insertText', false, pastedText)
      }
      const el = divRef.current
      if (el) onChange(el.innerHTML)
    },
    [sanitizePaste, onChange]
  )

  const minHeight = rows * 24

  return (
    <div className="space-y-1">
      <RichTextToolbar
        editableRef={divRef}
        value={value}
        onChange={onChange}
        disabled={disabled}
      />
      <div
        ref={divRef}
        contentEditable={!disabled}
        suppressContentEditableWarning
        onInput={handleInput}
        onKeyDown={handleKeyDown}
        onPaste={handlePaste}
        data-placeholder={placeholder}
        className={`w-full rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none transition resize-none overflow-auto [&:empty::before]:content-[attr(data-placeholder)] [&:empty::before]:text-slate-500 [&_mark]:rounded-sm [&_mark]:px-0.5 [&_b]:font-bold [&_strong]:font-bold ${className}`}
        style={{ minHeight }}
      />
    </div>
  )
}

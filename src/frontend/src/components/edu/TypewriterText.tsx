import { useCallback, useEffect, useRef, useState } from 'react'
import CoachMessageText from './CoachMessageText'

type Props = {
  text: string
  active: boolean
  onComplete?: () => void
  onProgress?: () => void
}

function msPerChar(len: number): number {
  if (len <= 60) return 22
  if (len <= 120) return 16
  if (len <= 240) return 12
  return 8
}

/** 응답 도착 후 글자 단위 타이핑. 탭/클릭 시 즉시 완료 */
export default function TypewriterText({ text, active, onComplete, onProgress }: Props) {
  const [displayed, setDisplayed] = useState(active ? '' : text)
  const [done, setDone] = useState(!active)
  const indexRef = useRef(0)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const onCompleteRef = useRef(onComplete)
  onCompleteRef.current = onComplete
  const onProgressRef = useRef(onProgress)
  onProgressRef.current = onProgress

  const finish = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
    setDisplayed(text)
    setDone(true)
    onCompleteRef.current?.()
  }, [text])

  useEffect(() => {
    if (!active) {
      setDisplayed(text)
      setDone(true)
      return
    }

    indexRef.current = 0
    setDisplayed('')
    setDone(false)

    const tick = () => {
      indexRef.current += 1
      const next = text.slice(0, indexRef.current)
      setDisplayed(next)
      onProgressRef.current?.()

      if (indexRef.current >= text.length) {
        setDone(true)
        onCompleteRef.current?.()
        return
      }
      timerRef.current = setTimeout(tick, msPerChar(text.length))
    }

    timerRef.current = setTimeout(tick, msPerChar(text.length))
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current)
    }
  }, [text, active])

  if (!active) {
    return <CoachMessageText text={text} />
  }

  return (
    <span
      role="button"
      tabIndex={0}
      onClick={done ? undefined : finish}
      onKeyDown={(e) => {
        if (!done && (e.key === 'Enter' || e.key === ' ')) finish()
      }}
      className={done ? undefined : 'cursor-pointer'}
      title={done ? undefined : '탭하면 전체 보기'}
    >
      <CoachMessageText text={displayed} hideIncompleteBold />
      {!done && (
        <span
          className="inline-block w-0.5 h-[1em] ml-0.5 align-text-bottom animate-pulse"
          style={{ backgroundColor: 'currentColor', opacity: 0.7 }}
          aria-hidden
        />
      )}
    </span>
  )
}

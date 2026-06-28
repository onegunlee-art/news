import { useState } from 'react'
import EduCoachLevelIcon from './EduCoachLevelIcon'
import {
  EDU_COACH_LEVELS,
  EDU_COACH_LEVEL_MEDAL,
  type EduCoachLevelInfo,
} from '../../constants/eduCoachLevel'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

type Props = {
  coachLevel: EduCoachLevelInfo
  size?: 'sm' | 'md' | 'lg'
  /** 서버 allowlist + level_debug URL — 일반 유저 false */
  debugSwitchEnabled?: boolean
  onSelectLevel?: (level: number) => void | Promise<void>
  className?: string
}

export default function EduCoachLevelBadge({
  coachLevel,
  size = 'md',
  debugSwitchEnabled = false,
  onSelectLevel,
  className = '',
}: Props) {
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const medal = EDU_COACH_LEVEL_MEDAL[coachLevel.coach_level] ?? EDU_COACH_LEVEL_MEDAL[1]
  const iconPx = size === 'lg' ? 28 : size === 'sm' ? 18 : 22
  const dim =
    size === 'lg'
      ? { circle: 'w-14 h-14', label: '1.125rem', sub: eduGame.fontSize.body }
      : size === 'sm'
        ? { circle: 'w-9 h-9', label: eduGame.fontSize.caption, sub: '0.65rem' }
        : { circle: 'w-12 h-12', label: eduGame.fontSize.label, sub: eduGame.fontSize.caption }

  const handlePick = async (level: number) => {
    if (!onSelectLevel || level === coachLevel.coach_level) {
      setOpen(false)
      return
    }
    setBusy(true)
    try {
      await onSelectLevel(level)
      setOpen(false)
    } finally {
      setBusy(false)
    }
  }

  const badgeInner = (
    <>
      <div
        className={`${dim.circle} rounded-full border-2 flex items-center justify-center shrink-0 shadow-sm`}
        style={{ backgroundColor: medal.bg, borderColor: medal.ring }}
        aria-hidden
      >
        <EduCoachLevelIcon level={coachLevel.coach_level} size={iconPx} />
      </div>
      <div className="min-w-0 text-left">
        <p className="font-bold truncate" style={{ fontSize: dim.label, color: eduGame.ink }}>
          {coachLevel.label_ko}
        </p>
        {size !== 'sm' && (
          <p style={{ fontSize: dim.sub, color: eduGame.muted }}>
            L{coachLevel.coach_level} · {coachLevel.label_en}
          </p>
        )}
      </div>
    </>
  )

  if (!debugSwitchEnabled || !onSelectLevel) {
    return (
      <div
        className={`flex items-center gap-2.5 ${className}`}
        aria-label={`코치 레벨 ${coachLevel.label_ko}`}
      >
        {badgeInner}
      </div>
    )
  }

  return (
    <div className={`relative ${className}`}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        disabled={busy}
        className={`flex items-center gap-2.5 rounded-xl border-2 px-2 py-1.5 transition-colors ${eduGameClasses.textKo}`}
        style={{
          borderColor: open ? eduGame.primary : eduGame.border,
          backgroundColor: open ? eduGame.primaryLight : eduGame.bg,
        }}
        aria-label={`코치 레벨 ${coachLevel.label_ko}, 탭하여 테스트 전환`}
        aria-expanded={open}
      >
        {badgeInner}
        <span className="text-xs shrink-0" style={{ color: eduGame.primary }}>
          ▾
        </span>
      </button>

      {open && (
        <>
          <button
            type="button"
            className="fixed inset-0 z-40 cursor-default"
            aria-label="닫기"
            onClick={() => setOpen(false)}
          />
          <div
            className={`absolute z-50 mt-2 left-0 right-0 min-w-[14rem] rounded-xl border-2 shadow-lg overflow-hidden ${eduGameClasses.textKo}`}
            style={{ borderColor: eduGame.primary, backgroundColor: eduGame.bg }}
            role="listbox"
            aria-label="코치 레벨 선택"
          >
            <p
              className="px-3 py-2 border-b font-bold"
              style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted, borderColor: eduGame.border }}
            >
              테스트 — 다음 퀘스트부터 적용
            </p>
            {EDU_COACH_LEVELS.map((item) => {
              const m = EDU_COACH_LEVEL_MEDAL[item.coach_level]
              const active = item.coach_level === coachLevel.coach_level
              return (
                <button
                  key={item.coach_level}
                  type="button"
                  role="option"
                  aria-selected={active}
                  disabled={busy}
                  onClick={() => void handlePick(item.coach_level)}
                  className="w-full flex items-center gap-3 px-3 py-2.5 text-left disabled:opacity-50"
                  style={{
                    backgroundColor: active ? eduGame.primaryLight : 'transparent',
                  }}
                >
                  <span
                    className="w-8 h-8 rounded-full border-2 flex items-center justify-center shrink-0"
                    style={{ backgroundColor: m.bg, borderColor: m.ring }}
                  >
                    <EduCoachLevelIcon level={item.coach_level} size={18} />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="font-bold block" style={{ color: eduGame.ink }}>
                      {item.label_ko}
                    </span>
                    <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                      L{item.coach_level} · {item.label_en}
                    </span>
                  </span>
                  {active ? (
                    <span style={{ fontSize: eduGame.fontSize.caption, color: eduGame.primary }}>✓</span>
                  ) : null}
                </button>
              )
            })}
          </div>
        </>
      )}
    </div>
  )
}

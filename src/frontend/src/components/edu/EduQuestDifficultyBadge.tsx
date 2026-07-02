import EduCoachLevelIcon from './EduCoachLevelIcon'
import { eduCoachLevelByNumber } from '../../constants/eduCoachLevel'
import { eduGame } from '../../constants/eduGameTheme'
import type { EduQuestListItem } from '../../services/eduApi'

type Props = {
  quest: Pick<
    EduQuestListItem,
    'difficulty_level' | 'difficulty_label_ko' | 'difficulty_student_frame_ko'
  >
  size?: 'sm' | 'md'
  showHint?: boolean
}

/** L1 = 친숙·입문 (질 낮음 아님) — 긍정 프레임 */
export default function EduQuestDifficultyBadge({
  quest,
  size = 'sm',
  showHint = false,
}: Props) {
  const level = quest.difficulty_level
  if (level == null || level < 1 || level > 5) {
    return null
  }

  const label =
    quest.difficulty_label_ko ?? eduCoachLevelByNumber(level).label_ko
  const isL1 = level === 1
  const hint =
    quest.difficulty_student_frame_ko ??
    (isL1 ? '시작하기 좋은 글 · 친숙한 주제' : null)

  const iconSize = size === 'md' ? 18 : 14
  const fontSize = size === 'md' ? '0.75rem' : '0.65rem'

  return (
    <span className="inline-flex flex-col gap-0.5 max-w-full">
      <span
        className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium"
        style={{
          fontSize,
          backgroundColor: isL1 ? '#E8F5E9' : eduGame.surface,
          color: isL1 ? '#2E7D32' : eduGame.ink,
          border: isL1 ? '1px solid #A5D6A7' : `1px solid ${eduGame.border}`,
        }}
      >
        <EduCoachLevelIcon level={level} size={iconSize} />
        <span>
          L{level} · {label}
        </span>
      </span>
      {showHint && hint && (
        <span
          className="pl-0.5 line-clamp-1"
          style={{ fontSize: '0.625rem', color: isL1 ? '#388E3C' : eduGame.muted }}
        >
          {hint}
        </span>
      )}
    </span>
  )
}

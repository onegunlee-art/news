import EduQuestCoverHero from './EduQuestCoverHero'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import type { EduQuestListItem } from '../../services/eduApi'

type Props = {
  quest: EduQuestListItem
  size?: 'featured' | 'compact'
  disabled?: boolean
  onSelect: (questId: string) => void
}

/** 홈 보드 퀘스트 카드 — 탭 시 해당 퀘스트 시작 */
export default function EduQuestBoardCard({
  quest,
  size = 'compact',
  disabled = false,
  onSelect,
}: Props) {
  const hookLine = quest.hook_short?.trim() || quest.lens_label || quest.conflict_summary

  return (
    <button
      type="button"
      onClick={() => onSelect(quest.quest_id)}
      disabled={disabled}
      className={`w-full text-left rounded-2xl border-2 overflow-hidden transition-all active:scale-[0.99] disabled:opacity-50 ${eduGameClasses.textKo}`}
      style={{
        borderColor: eduGame.border,
        backgroundColor: eduGame.bg,
      }}
    >
      <EduQuestCoverHero
        coverImageUrl={quest.cover_image_url}
        questTitle={quest.quest_title}
        hookShort={quest.hook_short}
        timeAnchor={quest.time_anchor}
        variant={size === 'featured' ? 'hero' : 'card'}
        topicLabel="따질 주제"
      />
      <div className={size === 'featured' ? 'p-4 space-y-2' : 'p-3 space-y-1.5'}>
        <div className="flex flex-wrap items-center gap-2">
          {quest.shelf_label && (
            <span
              className="px-2 py-0.5 rounded-full font-medium"
              style={{
                fontSize: '0.65rem',
                backgroundColor: eduGame.surface,
                color: eduGame.muted,
              }}
            >
              {quest.shelf_label}
            </span>
          )}
          {quest.completed && (
            <span
              className="ml-auto font-bold"
              style={{ fontSize: eduGame.fontSize.caption, color: '#2e7d32' }}
            >
              완료
            </span>
          )}
        </div>
        <p
          className="font-bold leading-snug"
          style={{
            fontSize: size === 'featured' ? '1.0625rem' : eduGame.fontSize.label,
            color: eduGame.ink,
          }}
        >
          {quest.quest_title}
        </p>
        {hookLine && (
          <p
            className="line-clamp-2"
            style={{
              fontSize: eduGame.fontSize.caption,
              color: eduGame.muted,
              lineHeight: eduGame.lineHeight.body,
            }}
          >
            {hookLine}
          </p>
        )}
      </div>
    </button>
  )
}

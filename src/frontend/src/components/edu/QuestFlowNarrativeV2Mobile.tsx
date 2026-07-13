import { AnimatePresence, motion } from 'framer-motion'
import CoachMessageText from './CoachMessageText'
import EduArticleSnippetCard from './EduArticleSnippetCard'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduEssayAssemblePanel from './EduEssayAssemblePanel'
import EduEssayCompletionPanel from './EduEssayCompletionPanel'
import EduQuestComboContinue from './EduQuestComboContinue'
import EduQuestCompletionCelebration from './EduQuestCompletionCelebration'
import EduQuestHomeButton from './EduQuestHomeButton'
import EduThoughtBoardPanel from './EduThoughtBoardPanel'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import {
  coachMessageHasSnippet,
  parseCoachAssistantMessage,
  splitCoachParagraphs,
} from '../../utils/eduCoachMessageParse'
import {
  lastAssistantIndex,
  lastStudentAnswer,
  NARRATIVE_V2_PAGE_MAX,
  type QuestFlowNarrativeV2ViewProps,
} from './questFlowNarrativeV2Shared'

function parseCoachCardContent(content: string): {
  question: string
  snippets: Array<{ display: string; value: string }>
} {
  if (!coachMessageHasSnippet(content)) {
    return { question: content, snippets: [] }
  }
  const segments = parseCoachAssistantMessage(content)
  const question = segments
    .filter(s => s.type === 'text')
    .map(s => s.value)
    .join('\n\n')
    .trim()
  const snippets = segments
    .filter(s => s.type === 'snippet')
    .map(s => ({ display: s.display, value: s.value }))
  return { question: question || content, snippets }
}

type Props = QuestFlowNarrativeV2ViewProps & { mobileCompact: boolean }

/** ≤639px — 기존 레이아웃 동결 */
export default function QuestFlowNarrativeV2Mobile(props: Props) {
  const {
    quest,
    sessionId,
    dialogue,
    board,
    choices,
    inputMode,
    textInput,
    setTextInput,
    inputFocused,
    setInputFocused,
    pulseLayer,
    boardCollapsed,
    toggleBoardCollapsed,
    turnCount,
    progressPct,
    phase,
    sending,
    assembling,
    composeReady,
    completed,
    error,
    essay,
    setEssay,
    xpGained,
    xpBreakdown,
    tier,
    coachLevel,
    levelUp,
    todayComboCount,
    playEssayReveal,
    setPlayEssayReveal,
    saveStatus,
    displayName,
    filledCount,
    coachPrompt,
    inputRef,
    handleChoice,
    handleTextSubmit,
    handleAnimComplete,
    viewportHeight,
    viewportOffsetTop,
    keyboardInset,
    mobileCompact,
  } = props

  const coachIndex = lastAssistantIndex(dialogue)
  const coachTurn = coachIndex >= 0 ? dialogue[coachIndex] : null
  const coachMessage = (coachTurn?.content ?? '').trim() || coachPrompt
  const cardContent = parseCoachCardContent(coachMessage)
  const cardParagraphs = splitCoachParagraphs(cardContent.question)
  const cardKey = `${phase}-${coachIndex}-${dialogue.length}-${inputMode}-${choices.map(c => c.id).join('|')}`
  const keyboardOpen = keyboardInset > 40 || inputFocused
  const showTextInput = inputMode === 'text' && !assembling
  const waitingLabel = sending ? '코치가 읽는 중…' : '탐구를 이어가고 있어요…'

  if (completed && essay) {
    return (
      <div className={`mx-auto min-h-dvh px-4 py-4 ${NARRATIVE_V2_PAGE_MAX}`} style={{ fontFamily: eduGame.fontBody }}>
        <EduQuestCompletionCelebration xpGained={xpGained} xpBreakdown={xpBreakdown} levelUp={levelUp} streakDays={tier?.streak_days ?? 0} coachLevel={coachLevel} tier={tier} active={completed} />
        <EduEssayCompletionPanel essay={essay} structure={null} onChange={setEssay} authorName={displayName} playReveal={playEssayReveal} onRevealComplete={() => setPlayEssayReveal(false)} saveStatus={saveStatus} />
        {quest?.quest_id && <EduQuestComboContinue currentQuestId={quest.quest_id} diversity={{ questFrame: quest.quest_frame ?? null }} comboCount={todayComboCount} uiMode="cards" />}
      </div>
    )
  }

  return (
    <div
      className={`${eduGameClasses.chatShell} fixed left-0 right-0 flex flex-col overflow-hidden`}
      style={{
        color: eduGame.ink,
        fontFamily: eduGame.fontBody,
        backgroundColor: eduGame.bg,
        top: viewportHeight != null ? viewportOffsetTop : 0,
        height: viewportHeight ?? '100dvh',
        maxHeight: viewportHeight ?? '100dvh',
      }}
    >
      <header
        className={`shrink-0 border-b ${NARRATIVE_V2_PAGE_MAX} mx-auto w-full px-3 py-2`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingTop: 'max(0.375rem, env(safe-area-inset-top, 0px))',
        }}
      >
        <div className="flex items-center gap-2">
          <EduQuestHomeButton />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-bold">{quest?.quest_title ?? '오늘의 탐구'}</p>
            <p className="text-xs" style={{ color: eduGame.muted }}>
              {displayName} · {turnCount}턴 · 생각판 {filledCount}/6
            </p>
          </div>
          <span className="text-xs font-bold tabular-nums" style={{ color: eduGame.primary }}>
            {progressPct}%
          </span>
        </div>
      </header>

      <div className={`${NARRATIVE_V2_PAGE_MAX} mx-auto w-full shrink-0`}>
        <EduThoughtBoardPanel
          board={board}
          pulseLayer={pulseLayer}
          collapsed={boardCollapsed}
          onToggle={toggleBoardCollapsed}
          filledCount={filledCount}
          compact={mobileCompact}
        />
      </div>

      <div className={`flex-1 min-h-0 flex flex-col overflow-hidden ${NARRATIVE_V2_PAGE_MAX} mx-auto w-full`}>
        <AnimatePresence mode="wait">
          <motion.div
            key={cardKey}
            initial={{ opacity: 0, x: 40 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -40 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
            className="flex-1 min-h-0 flex flex-col overflow-hidden"
          >
            {sending && !assembling ? (
              <div className="flex-1 min-h-0 flex flex-col justify-center px-4 py-6">
                <EduCoachWaitingPanel studentAnswer={lastStudentAnswer(dialogue)} label={waitingLabel} compact />
              </div>
            ) : (
              <div
                className={`flex-1 min-h-0 overflow-y-auto px-4 pt-3 pb-2 ${mobileCompact ? 'flex flex-col justify-center' : ''}`}
              >
                <div className="space-y-4">
                  {cardParagraphs.map((paragraph, i) => (
                    <p
                      key={`${cardKey}-p-${i}`}
                      className={`text-center font-bold ${eduGameClasses.textKoPre}`}
                      style={{
                        fontSize: keyboardOpen ? '1.0625rem' : mobileCompact ? '1.125rem' : '1.25rem',
                        lineHeight: 1.55,
                        color: eduGame.ink,
                      }}
                    >
                      <CoachMessageText text={paragraph} />
                    </p>
                  ))}
                </div>

                {cardContent.snippets.length > 0 && (
                  <div
                    className="mt-3 space-y-2 overflow-y-auto"
                    style={{ maxHeight: keyboardOpen ? '14vh' : mobileCompact ? '18vh' : '24vh' }}
                  >
                    {cardContent.snippets.map((snip, i) => (
                      <EduArticleSnippetCard key={`${cardKey}-snip-${i}`} text={snip.value} display={snip.display} />
                    ))}
                  </div>
                )}
              </div>
            )}
          </motion.div>
        </AnimatePresence>
      </div>

      {error && <p className={`mx-auto ${NARRATIVE_V2_PAGE_MAX} w-full px-4 pb-2 text-sm text-red-600`}>{error}</p>}

      <footer
        className={`shrink-0 border-t ${NARRATIVE_V2_PAGE_MAX} mx-auto w-full px-4`}
        style={{
          borderColor: eduGame.border,
          backgroundColor: eduGame.bg,
          paddingTop: keyboardOpen ? '0.375rem' : '0.625rem',
          paddingBottom: keyboardOpen
            ? `calc(0.375rem + ${Math.max(keyboardInset, 0)}px + env(safe-area-inset-bottom, 0px))`
            : 'calc(0.625rem + env(safe-area-inset-bottom, 0px))',
        }}
      >
        {showTextInput ? (
          <div className="space-y-2">
            <textarea
              ref={inputRef}
              value={textInput}
              onChange={e => setTextInput(e.target.value)}
              onFocus={() => setInputFocused(true)}
              onBlur={() => window.setTimeout(() => setInputFocused(false), 100)}
              rows={keyboardOpen ? 2 : 3}
              placeholder="네 생각을 한두 문장으로 써 봐…"
              className={`w-full resize-none ${eduGameClasses.input}`}
              style={{
                borderColor: eduGame.border,
                fontSize: eduGame.fontSize.body,
                maxHeight: keyboardOpen ? '4.75rem' : '7rem',
              }}
              disabled={sending || assembling}
            />
            <button
              type="button"
              disabled={sending || assembling || !textInput.trim()}
              onClick={() => void handleTextSubmit()}
              className={`${eduGameClasses.btnPrimary} w-full py-4`}
              style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
            >
              {sending ? '보내는 중…' : '내 결론 보내기'}
            </button>
          </div>
        ) : choices.length > 0 ? (
          <div className="space-y-2.5" role="group" aria-label="선택지">
            {choices.map((c, i) => (
              <button
                key={c.id}
                type="button"
                disabled={sending || assembling}
                onClick={() => void handleChoice(c)}
                className={`w-full py-4 px-4 rounded-2xl font-bold border-2 text-center active:scale-[0.98] transition-transform disabled:opacity-40 disabled:active:scale-100 ${eduGameClasses.textKo}`}
                style={{
                  fontSize: '1.0625rem',
                  lineHeight: 1.45,
                  borderColor: eduGame.primary,
                  backgroundColor: i === 0 ? eduGame.primary : eduGame.bg,
                  color: i === 0 ? eduGame.bg : eduGame.ink,
                  boxShadow: i === 0 ? `0 2px 0 ${eduGame.primaryDark}59` : `0 2px 0 ${eduGame.border}`,
                }}
              >
                {c.label}
              </button>
            ))}
          </div>
        ) : null}
      </footer>

      {assembling && sessionId ? (
        <EduEssayAssemblePanel
          board={board}
          questTitle={quest?.quest_title}
          composeReady={composeReady}
          onAnimComplete={handleAnimComplete}
        />
      ) : null}
    </div>
  )
}

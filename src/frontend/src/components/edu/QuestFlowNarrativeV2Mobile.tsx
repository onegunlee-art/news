import { AnimatePresence, motion } from 'framer-motion'
import CoachMessageText from './CoachMessageText'
import EduArticleSnippetCard from './EduArticleSnippetCard'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduComposeWaitPanel from './EduComposeWaitPanel'
import EduQuestHomeButton from './EduQuestHomeButton'
import EduMobileBoardStrip from './EduMobileBoardStrip'
import EduThoughtBoardPanel from './EduThoughtBoardPanel'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { lastStudentAnswer } from './questFlowNarrativeV2Utils'
import { QUEST_FLOW_PAGE_MAX, type QuestFlowNarrativeV2ViewProps } from './questFlowNarrativeV2Shared'

/** ≤639px — Phase A JSX 동결 (픽셀 동일) */
export default function QuestFlowNarrativeV2Mobile(props: QuestFlowNarrativeV2ViewProps) {
  const {
    quest,
    sessionId,
    dialogue,
    board,
    choices,
    textInput,
    setTextInput,
    pulseLayer,
    boardCollapsed,
    toggleBoardCollapsed,
    openBoardPanel,
    boardFocusLayer,
    turnCount,
    progressPct,
    sending,
    assembling,
    completed,
    error,
    cardContent,
    cardParagraphs,
    cardKey,
    keyboardOpen,
    showTextInput,
    waitingLabel,
    displayName,
    filledCount,
    mobileCompact,
    viewportHeight,
    viewportOffsetTop,
    keyboardInset,
    setInputFocused,
    inputRef,
    onChoice,
    onTextSubmit,
  } = props

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
        className={`shrink-0 border-b ${QUEST_FLOW_PAGE_MAX} mx-auto w-full px-3 py-2`}
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

      <EduMobileBoardStrip
        board={board}
        hidden={keyboardOpen || assembling || completed}
        onChipTap={layerId => openBoardPanel(layerId)}
      />

      <div className={`${QUEST_FLOW_PAGE_MAX} mx-auto w-full shrink-0`}>
        <EduThoughtBoardPanel
          board={board}
          pulseLayer={pulseLayer}
          focusLayer={boardFocusLayer}
          collapsed={boardCollapsed}
          onToggle={toggleBoardCollapsed}
          filledCount={filledCount}
          compact={mobileCompact}
        />
      </div>

      <div className={`flex-1 min-h-0 flex flex-col overflow-hidden ${QUEST_FLOW_PAGE_MAX} mx-auto w-full`}>
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

      {error && <p className={`mx-auto ${QUEST_FLOW_PAGE_MAX} w-full px-4 pb-2 text-sm text-red-600`}>{error}</p>}

      <footer
        className={`shrink-0 border-t ${QUEST_FLOW_PAGE_MAX} mx-auto w-full px-4`}
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
              onClick={() => void onTextSubmit()}
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
                onClick={() => void onChoice(c)}
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
        <EduComposeWaitPanel board={board} questTitle={quest?.quest_title} turnCount={turnCount} />
      ) : null}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import CoachMessageText from './CoachMessageText'
import EduArticleSnippetCard from './EduArticleSnippetCard'
import EduCoachWaitingPanel from './EduCoachWaitingPanel'
import EduComposeWaitPanel from './EduComposeWaitPanel'
import EduPcEssayCompletionPanel from './EduPcEssayCompletionPanel'
import EduPcJourneyTimeline from './EduPcJourneyTimeline'
import EduPcTechTransparencyPanel from './EduPcTechTransparencyPanel'
import EduPcThoughtBoardColumn from './EduPcThoughtBoardColumn'
import EduQuestCompletionCelebration from './EduQuestCompletionCelebration'
import EduQuestComboContinue from './EduQuestComboContinue'
import { eduPc, eduPcClasses, eduPcShellStyle, eduPcTopGlowStyle } from '../../constants/eduPcRedesignTheme'
import { getEduStudent } from '../../services/eduApi'
import { lastStudentAnswer } from './questFlowNarrativeV2Utils'
import type { QuestFlowCompletionProps, QuestFlowNarrativeV2ViewProps } from './questFlowNarrativeV2Shared'

type PcProps = QuestFlowNarrativeV2ViewProps & {
  completion?: QuestFlowCompletionProps | null
}

function EduPcScanBar() {
  return (
    <div className="h-1 w-full overflow-hidden rounded-full" style={{ backgroundColor: eduPc.border }}>
      <div
        className="h-full w-1/3 animate-[eduPcScan_1.2s_ease-in-out_infinite]"
        style={{ background: eduPc.scanBar }}
      />
      <style>{`
        @keyframes eduPcScan {
          0% { transform: translateX(-100%); }
          100% { transform: translateX(400%); }
        }
      `}</style>
    </div>
  )
}

/** ≥640px — PC 3분할 UI */
export default function QuestFlowNarrativeV2Pc({ completion, ...props }: PcProps) {
  const [searchParams] = useSearchParams()
  const [techVisible, setTechVisible] = useState(
    () => searchParams.get('tech_transparency') === '1'
  )

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 't' || e.key === 'T') {
        const tag = (e.target as HTMLElement)?.tagName
        if (tag === 'INPUT' || tag === 'TEXTAREA') return
        setTechVisible(v => !v)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const {
    quest,
    sessionId,
    dialogue,
    board,
    choices,
    textInput,
    setTextInput,
    pulseLayer,
    turnCount,
    progressPct,
    phase,
    narrativeV2Node,
    sending,
    assembling,
    error,
    cardContent,
    cardParagraphs,
    cardKey,
    showTextInput,
    waitingLabel,
    displayName,
    filledCount,
    inputRef,
    onChoice,
    onTextSubmit,
  } = props

  const coachLevel = getEduStudent()?.coach_level ?? 1

  if (completion) {
    return (
      <div className={eduPcClasses.shell} style={eduPcShellStyle()}>
        <div className={eduPcClasses.topGlow} style={eduPcTopGlowStyle()} aria-hidden />
        <header
          className="shrink-0 border-b px-6 py-3 flex items-center justify-between"
          style={{ borderColor: eduPc.border }}
        >
          <div>
            <p className="text-xs font-bold tracking-wide" style={{ color: eduPc.primary }}>
              gistudy
            </p>
            <h1 className="text-lg font-bold" style={{ fontFamily: eduPc.fontSerif }}>
              {quest?.quest_title ?? '오늘의 탐구'}
            </h1>
          </div>
          <span
            className="rounded-full px-3 py-1 text-xs font-bold tabular-nums border"
            style={{ borderColor: eduPc.borderStrong, color: eduPc.primary }}
          >
            {turnCount}턴
          </span>
        </header>
        <EduQuestCompletionCelebration
          xpGained={completion.xpGained}
          xpBreakdown={completion.xpBreakdown}
          levelUp={completion.levelUp}
          streakDays={completion.tier?.streak_days ?? 0}
          coachLevel={completion.coachLevel}
          tier={completion.tier}
          active
        />
        <EduPcEssayCompletionPanel
          essay={completion.essay}
          board={board}
          onChange={completion.setEssay}
          saveStatus={completion.saveStatus}
        />
        {quest?.quest_id ? (
          <div className="px-6 pb-6">
            <EduQuestComboContinue
              currentQuestId={quest.quest_id}
              diversity={{ questFrame: quest.quest_frame ?? null }}
              comboCount={completion.todayComboCount}
              uiMode="cards"
            />
          </div>
        ) : null}
      </div>
    )
  }

  return (
    <div className={eduPcClasses.shell} style={eduPcShellStyle()}>
      <div className={eduPcClasses.topGlow} style={eduPcTopGlowStyle()} aria-hidden />

      <header
        className="shrink-0 border-b px-5 py-3 flex items-center gap-4 z-10"
        style={{ borderColor: eduPc.border, backgroundColor: 'rgba(7,7,7,0.85)' }}
      >
        <Link to="/edu" className="text-xs font-bold shrink-0" style={{ color: eduPc.primary }}>
          ● gistudy
        </Link>
        <div className="min-w-0 flex-1">
          <h1 className="truncate text-base font-bold" style={{ fontFamily: eduPc.fontSerif }}>
            {quest?.quest_title ?? '오늘의 탐구'}
          </h1>
          <p className="text-xs truncate" style={{ color: eduPc.textMuted }}>
            {displayName}
          </p>
        </div>
        <span
          className="shrink-0 rounded-full px-3 py-1 text-xs font-bold tabular-nums border"
          style={{ borderColor: eduPc.borderStrong, color: eduPc.primary }}
        >
          {turnCount}턴 · {progressPct}%
        </span>
      </header>

      <div className="flex-1 min-h-0 flex overflow-hidden z-10">
        <EduPcJourneyTimeline
          board={board}
          pulseLayer={pulseLayer}
          narrativeV2Node={narrativeV2Node}
        />

        <main className="flex-1 min-w-0 flex flex-col overflow-hidden">
          <p
            className="shrink-0 text-center text-xs py-2 border-b"
            style={{ borderColor: eduPc.border, color: eduPc.textMuted }}
          >
            AI 코치와 대화 중 · 답을 주지 않고 되묻습니다
          </p>

          <div className="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
            <AnimatePresence mode="wait">
              <motion.div
                key={cardKey}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.25 }}
                className="max-w-2xl"
              >
                {sending && !assembling ? (
                  <div className="space-y-4 py-8">
                    <EduPcScanBar />
                    <EduCoachWaitingPanel
                      studentAnswer={lastStudentAnswer(dialogue)}
                      label={waitingLabel}
                      compact
                    />
                  </div>
                ) : (
                  <>
                    {dialogue
                      .filter(t => t.role === 'student' && (t.content ?? '').trim())
                      .slice(-3)
                      .map((turn, i) => (
                        <div key={`stu-${i}`} className="flex justify-end mb-3">
                          <p
                            className={`max-w-[85%] rounded-2xl rounded-br-md px-4 py-3 text-sm ${eduPcClasses.textKo}`}
                            style={{
                              backgroundColor: 'rgba(232,93,44,0.2)',
                              border: `1px solid ${eduPc.borderStrong}`,
                              color: eduPc.text,
                            }}
                          >
                            {turn.content}
                          </p>
                        </div>
                      ))}
                    <div className="space-y-4">
                      {cardParagraphs.map((paragraph, i) => (
                        <p
                          key={`${cardKey}-p-${i}`}
                          className={`font-bold leading-relaxed ${eduPcClasses.textKo}`}
                          style={{
                            fontFamily: eduPc.fontSerif,
                            fontSize: eduPc.coachSize,
                            color: eduPc.text,
                          }}
                        >
                          <CoachMessageText text={paragraph} />
                        </p>
                      ))}
                    </div>
                    {cardContent.snippets.length > 0 ? (
                      <div className="mt-4 space-y-2 max-h-[28vh] overflow-y-auto">
                        {cardContent.snippets.map((snip, i) => (
                          <EduArticleSnippetCard key={`${cardKey}-snip-${i}`} text={snip.value} display={snip.display} />
                        ))}
                      </div>
                    ) : null}
                  </>
                )}
              </motion.div>
            </AnimatePresence>
          </div>

          {error ? <p className="shrink-0 px-6 pb-2 text-sm text-red-400">{error}</p> : null}

          <footer
            className="shrink-0 border-t px-6 py-4"
            style={{ borderColor: eduPc.border, backgroundColor: 'rgba(7,7,7,0.9)' }}
          >
            {showTextInput ? (
              <div className="max-w-2xl space-y-3">
                <textarea
                  ref={inputRef}
                  value={textInput}
                  onChange={e => setTextInput(e.target.value)}
                  rows={3}
                  placeholder="네 생각을 한두 문장으로 써 봐…"
                  className={`w-full resize-none rounded-xl border px-4 py-3 text-sm ${eduPcClasses.textKo}`}
                  style={{
                    borderColor: eduPc.borderStrong,
                    backgroundColor: eduPc.surface,
                    color: eduPc.text,
                  }}
                  disabled={sending || assembling}
                />
                <button
                  type="button"
                  disabled={sending || assembling || !textInput.trim()}
                  onClick={() => void onTextSubmit()}
                  className="rounded-xl px-5 py-3 text-sm font-bold disabled:opacity-40"
                  style={{ backgroundColor: eduPc.primary, color: eduPc.text }}
                >
                  {sending ? '보내는 중…' : '내 결론 보내기'}
                </button>
              </div>
            ) : choices.length > 0 ? (
              <div className="max-w-2xl space-y-2" role="group" aria-label="선택지">
                {choices.map(c => (
                  <button
                    key={c.id}
                    type="button"
                    disabled={sending || assembling}
                    onClick={() => void onChoice(c)}
                    className={`w-full text-left rounded-xl border px-4 py-3.5 text-sm font-semibold transition-all hover:border-r-[3px] disabled:opacity-40 ${eduPcClasses.textKo}`}
                    style={{
                      borderColor: eduPc.borderStrong,
                      backgroundColor: eduPc.surface,
                      color: eduPc.text,
                    }}
                    onMouseEnter={e => {
                      e.currentTarget.style.borderColor = eduPc.primary
                      e.currentTarget.style.borderRightWidth = '3px'
                    }}
                    onMouseLeave={e => {
                      e.currentTarget.style.borderColor = eduPc.borderStrong
                      e.currentTarget.style.borderRightWidth = ''
                    }}
                  >
                    {c.label}
                  </button>
                ))}
              </div>
            ) : sending ? (
              <EduPcScanBar />
            ) : null}
          </footer>
        </main>

        <EduPcThoughtBoardColumn board={board} pulseLayer={pulseLayer} filledCount={filledCount} />
      </div>

      {assembling && sessionId ? (
        <EduComposeWaitPanel
          board={board}
          questTitle={quest?.quest_title}
          turnCount={turnCount}
          layout="wide"
        />
      ) : null}

      <EduPcTechTransparencyPanel
        phase={phase}
        narrativeV2Node={narrativeV2Node}
        turnCount={turnCount}
        filledCount={filledCount}
        board={board}
        coachLevel={coachLevel}
        inputMode={props.inputMode}
        sending={sending}
        assembling={assembling}
        visible={techVisible}
      />
    </div>
  )
}

import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import EduPcChoiceFooter from './pc/EduPcChoiceFooter'
import EduPcCoachDialogue from './pc/EduPcCoachDialogue'
import EduPcComposeLoading from './pc/EduPcComposeLoading'
import EduPcComposeScreen from './pc/EduPcComposeScreen'
import EduPcJourneyTimeline from './pc/EduPcJourneyTimeline'
import EduPcTechTransparencyPanel from './pc/EduPcTechTransparencyPanel'
import EduPcThoughtBoardSidebar from './pc/EduPcThoughtBoardSidebar'
import EduPcTopBar from './pc/EduPcTopBar'
import { eduPc, eduPcClasses } from '../../constants/eduPcRedesignTheme'
import { resolveEduTechTransparency } from '../../constants/eduTechTransparency'
import type { QuestFlowNarrativeV2ViewProps } from './questFlowNarrativeV2Shared'

type Props = QuestFlowNarrativeV2ViewProps

/** ≥640px — 3분할 PC 레이아웃 */
export default function QuestFlowNarrativeV2Pc(props: Props) {
  const [searchParams] = useSearchParams()
  const [techTransparency, setTechTransparency] = useState(() =>
    resolveEduTechTransparency(searchParams)
  )

  useEffect(() => {
    setTechTransparency(resolveEduTechTransparency(searchParams))
  }, [searchParams])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 't' || e.key === 'T') {
        if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return
        setTechTransparency(v => {
          const next = !v
          if (next) localStorage.setItem('edu_tech_transparency', '1')
          else localStorage.removeItem('edu_tech_transparency')
          return next
        })
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const {
    quest,
    dialogue,
    board,
    blueprint,
    choices,
    inputMode,
    textInput,
    setTextInput,
    setInputFocused,
    pulseLayer,
    turnCount,
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
    saveStatus,
    filledCount,
    inputRef,
    handleChoice,
    handleTextSubmit,
    persistEssay,
    phase,
  } = props

  const showTextInput = inputMode === 'text' && !assembling

  if (completed && essay) {
    return (
      <div className={eduPcClasses.shell} style={{ backgroundColor: eduPc.bg }}>
        <div className={eduPcClasses.gridBg} aria-hidden />
        <div className={eduPcClasses.topGlow} style={{ background: eduPc.topGlow }} aria-hidden />
        <EduPcTopBar
          questTitle={quest?.quest_title ?? '오늘의 탐구'}
          turnCount={turnCount}
          techTransparencyOn={techTransparency}
          onToggleTechTransparency={() =>
            setTechTransparency(v => {
              const next = !v
              if (next) localStorage.setItem('edu_tech_transparency', '1')
              else localStorage.removeItem('edu_tech_transparency')
              return next
            })
          }
        />
        <EduPcComposeScreen
          essay={essay}
          board={board}
          quest={quest}
          onChange={setEssay}
          onPersist={() => void persistEssay(essay)}
          saveStatus={saveStatus}
          xpGained={xpGained}
          xpBreakdown={xpBreakdown}
          levelUp={levelUp}
          tier={tier}
          coachLevel={coachLevel}
          todayComboCount={todayComboCount}
        />
        <EduPcTechTransparencyPanel
          blueprint={blueprint}
          phase={phase}
          dialogue={dialogue}
          filledCount={filledCount}
          turnCount={turnCount}
          coachLevel={blueprint?.coach_level}
          visible={techTransparency}
        />
      </div>
    )
  }

  return (
    <div className={`${eduPcClasses.shell} relative`} style={{ backgroundColor: eduPc.bg }}>
      <div className={eduPcClasses.gridBg} aria-hidden />
      <div className={eduPcClasses.topGlow} style={{ background: eduPc.topGlow }} aria-hidden />

      <EduPcTopBar
        questTitle={quest?.quest_title ?? '오늘의 탐구'}
        turnCount={turnCount}
        techTransparencyOn={techTransparency}
        onToggleTechTransparency={() =>
          setTechTransparency(v => {
            const next = !v
            if (next) localStorage.setItem('edu_tech_transparency', '1')
            else localStorage.removeItem('edu_tech_transparency')
            return next
          })
        }
      />

      <div className="relative flex flex-1 min-h-0 z-[1]">
        <EduPcJourneyTimeline board={board} pulseLayer={pulseLayer} />

        <main className="flex flex-col flex-1 min-w-0 min-h-0 relative">
          <EduPcCoachDialogue dialogue={dialogue} sending={sending} assembling={assembling} />
          {error ? (
            <p className="shrink-0 px-5 pb-2 text-sm text-red-400">{error}</p>
          ) : null}
          <EduPcChoiceFooter
            choices={choices}
            showTextInput={showTextInput}
            textInput={textInput}
            setTextInput={setTextInput}
            setInputFocused={setInputFocused}
            inputRef={inputRef}
            sending={sending}
            assembling={assembling}
            onChoice={c => void handleChoice(c)}
            onTextSubmit={() => void handleTextSubmit()}
          />
          {assembling ? <EduPcComposeLoading composeReady={composeReady} /> : null}
        </main>

        <EduPcThoughtBoardSidebar board={board} pulseLayer={pulseLayer} filledCount={filledCount} />
      </div>

      <EduPcTechTransparencyPanel
        blueprint={blueprint}
        phase={phase}
        dialogue={dialogue}
        filledCount={filledCount}
        turnCount={turnCount}
        coachLevel={blueprint?.coach_level}
        visible={techTransparency}
      />
    </div>
  )
}

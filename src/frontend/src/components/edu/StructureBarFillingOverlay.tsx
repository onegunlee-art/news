import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

/** 구조바 슬롯 — waiting 중 하단→상단 채워짐 */
export default function StructureBarFillingOverlay() {
  return (
    <div
      className={`pointer-events-none absolute inset-x-0 bottom-0 top-0 overflow-hidden rounded-[inherit] ${eduGameClasses.animAxisFilling}`}
      aria-hidden
    >
      <div
        className="absolute inset-x-0 bottom-0 edu-game-axis-fill-bar"
        style={{ backgroundColor: eduGame.primaryLight }}
      />
    </div>
  )
}

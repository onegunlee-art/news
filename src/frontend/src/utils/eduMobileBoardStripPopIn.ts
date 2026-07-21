/** 모바일 스트립 popIn 대상 판별 — view only, 대화/compose 게이트 0 */

/** state.php 복원(첫 hydrate)이면 popIn 없음 */
export function detectNewBoardChipIds(
  prevFilledIds: string[] | null,
  currentFilledIds: string[]
): string[] {
  if (prevFilledIds === null) return []
  return currentFilledIds.filter(id => !prevFilledIds.includes(id))
}

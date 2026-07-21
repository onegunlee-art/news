import { describe, expect, it } from 'vitest'
import { detectNewBoardChipIds } from './eduMobileBoardStripPopIn'

describe('detectNewBoardChipIds', () => {
  it('첫 hydrate(복원) — popIn 없음', () => {
    expect(detectNewBoardChipIds(null, ['stance', 'reason'])).toEqual([])
  })

  it('층 통과 — 새 칩만 popIn', () => {
    expect(detectNewBoardChipIds(['stance'], ['stance', 'reason'])).toEqual(['reason'])
  })

  it('보드 변화 없음 — 빈 배열', () => {
    expect(detectNewBoardChipIds(['stance', 'reason'], ['stance', 'reason'])).toEqual([])
  })

  it('연속 층 통과 — 누적 popIn', () => {
    let prev: string[] | null = null
    prev = ['stance']
    expect(detectNewBoardChipIds(prev, ['stance', 'reason'])).toEqual(['reason'])
    prev = ['stance', 'reason']
    expect(detectNewBoardChipIds(prev, ['stance', 'reason', 'depth'])).toEqual(['depth'])
  })
})

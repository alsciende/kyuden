import { describe, expect, it } from 'vitest'

import { MONS, PATTERNS, monOf, noboriLabel, noboriSizes, patternOf } from '@/data/nobori'
import { earnedCount, isEarned } from '@/lib/nobori'

describe('the nobori', () => {
  it('has the ensō and the seven Great Clans as mons, the ensō alone without ring', () => {
    expect(MONS.map((mon) => mon.id)).toEqual([
      'enso',
      'crab',
      'crane',
      'dragon',
      'lion',
      'phoenix',
      'scorpion',
      'unicorn',
    ])
    expect(MONS.filter((mon) => !mon.ringed).map((mon) => mon.id)).toEqual(['enso'])
  })

  it('draws every pattern from a tile but none and plain', () => {
    expect(PATTERNS.filter((pattern) => pattern.tile === null).map((pattern) => pattern.id)).toEqual(['none', 'plain'])
  })

  it('falls back on the ensō without pattern for a value it does not know', () => {
    expect(monOf('mantis').id).toBe('enso')
    expect(patternOf('paisley').id).toBe('none')
  })

  it('names the nobori by its mon and its pattern', () => {
    expect(noboriLabel({ mon: 'crab', pattern: 'seigaiha' })).toBe('Crab mon on a Seigaiha nobori')
    expect(noboriLabel({ mon: 'enso', pattern: 'none' })).toBe('Ensō mon on a nobori without pattern')
  })

  it('sizes the mon by the width of the nobori, the tiles never under 60%', () => {
    expect(noboriSizes(56)).toMatchObject({ mon: 39, disc: 48, scale: 0.6 })
    expect(noboriSizes(160)).toMatchObject({ mon: 110, scale: 1.6 })
  })

  it('tells what the user has earned', () => {
    const unlocked = { mons: ['enso' as const, 'crab' as const], patterns: ['none' as const] }

    expect(isEarned(unlocked, 'crab')).toBe(true)
    expect(isEarned(unlocked, 'crane')).toBe(false)
    expect(isEarned(unlocked, 'seigaiha')).toBe(false)
    expect(earnedCount(unlocked.mons, MONS)).toBe('2 of 8 earned')
  })
})

import { describe, expect, it } from 'vitest'

import { actionIcon } from '@/lib/actionIcons'

describe('actionIcon', () => {
  const payload = { card_id: 'c1' }

  it('draws the ways a card leaves where it is', () => {
    expect(actionIcon({ type: 'put_card_on_deck', payload: { ...payload, position: 'top' } })).toBe('top')
    expect(actionIcon({ type: 'put_card_on_deck', payload: { ...payload, position: 'bottom' } })).toBe('bottom')
    expect(actionIcon({ type: 'shuffle_into_deck', payload })).toBe('shuffle')
    expect(actionIcon({ type: 'discard_card', payload })).toBe('discard')
    expect(actionIcon({ type: 'return_card_to_hand', payload })).toBe('hand')
    expect(actionIcon({ type: 'add_card_to_hand', payload })).toBe('hand')
    expect(actionIcon({ type: 'sacrifice_card', payload })).toBe('sacrifice')
    expect(actionIcon({ type: 'discard_duplicate', payload: { ...payload, copy_id: 'c2' } })).toBe('duplicate')
  })

  it('draws pointing at a card', () => {
    expect(actionIcon({ type: 'point_at_card', payload })).toBe('point')
  })

  it('leaves the other actions to their label', () => {
    expect(actionIcon({ type: 'bow_card', payload })).toBeNull()
  })
})

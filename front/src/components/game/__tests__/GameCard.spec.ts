import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import type { CardDisplay } from '@/api/client'
import type { CardView } from '@/api/game'
import GameCard from '@/components/game/GameCard.vue'
import { card, printed } from '@/lib/__tests__/gameFixture'
import { session } from '@/lib/session'

enableAutoUnmount(afterEach)

const cards = {
  toturi: printed('toturi', { name: 'Akodo Toturi', military: '6', political: '3', image_url: '/toturi.webp' }),
  katana: printed('katana', { type: 'attachment', name: 'Fine Katana', military_bonus: '+2', image_url: null }),
}

function show(view: CardView, cardDisplay: CardDisplay, attachments: CardView[] = []) {
  session.value = {
    userId: 'john',
    name: 'John',
    username: 'john',
    preferredClan: 'lion',
    cardDisplay,
    theme: 'auto',
    token: 'l5r_a',
  }

  return mount(GameCard, { props: { card: view, cards, mine: true, attachments } })
}

describe('GameCard', () => {
  afterEach(() => {
    session.value = null
  })

  it('draws the card by default', () => {
    const wrapper = show(card('toturi'), 'drawn')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.name').text()).toBe('Akodo Toturi')
  })

  it('shows the image of the card when the player prefers it', () => {
    const wrapper = show(card('toturi'), 'images')

    expect(wrapper.find('img').attributes('src')).toBe('/toturi.webp')
    expect(wrapper.find('.name').exists()).toBe(false)
    expect(wrapper.find('.art-stats').exists()).toBe(false)
  })

  it('shows the back of the side of a face-down card', () => {
    const wrapper = show(card(null, { side: 'dynasty', orientation: 'face_down' }), 'images')

    expect(wrapper.find('img').attributes('src')).toContain('card-back-dynasty')
  })

  it('marks the skills that differ from the printed ones', () => {
    const wrapper = show(card('toturi'), 'images', [card('katana')])

    expect(wrapper.find('.art-stats').text()).toContain('8')
  })

  it('draws a card without an image', () => {
    const wrapper = show(card('katana'), 'images')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.name').text()).toBe('Fine Katana')
  })
})

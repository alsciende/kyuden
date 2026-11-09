import { enableAutoUnmount, mount, RouterLinkStub } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import type { LoggedMessage } from '@/api/game'
import type { ReplayView } from '@/api/replay'
import ReplayPanel from '@/components/game/ReplayPanel.vue'
import { game, player } from '@/lib/__tests__/gameFixture'

enableAutoUnmount(afterEach)

const me = player('me')
const opp = player('opp')

const log: LoggedMessage[] = [
  { version: 1, type: 'create_game', player_id: null, entries: [{ template: 'The game is created', params: [] }] },
  {
    version: 2,
    type: 'pass_opportunity',
    player_id: 'opp',
    entries: [{ template: '{player} passes', params: { player: { type: 'player', id: 'opp' } } }],
  },
  {
    version: 3,
    type: 'pass_opportunity',
    player_id: 'me',
    entries: [{ template: '{player} passes', params: { player: { type: 'player', id: 'me' } } }],
  },
]

function replay(changes: Partial<ReplayView> = {}): ReplayView {
  return {
    game_id: 'game',
    replay_game_id: 'copy',
    player_id: 'me',
    version: 2,
    total: 3,
    next: { version: 3, type: 'pass_opportunity' },
    ended: false,
    ...changes,
  }
}

function show(view: ReplayView, changes: Record<string, unknown> = {}) {
  return mount(ReplayPanel, {
    props: {
      replay: view,
      game: game(me, opp, { replaying: true, winner_id: 'me', victory_condition: 'concession' }),
      cards: {},
      log,
      stepFrom: 1,
      busy: false,
      jumping: null,
      autoplay: false,
      diverged: false,
      error: null,
      ...changes,
    },
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
}

describe('ReplayPanel', () => {
  it('tells where the replay stands, what happened since and what the player does next', () => {
    const wrapper = show(replay())

    expect(wrapper.find('.where').text()).toBe('Message 2 of 3')
    expect(wrapper.find('.since').text()).toBe('Opp. passes')
    expect(wrapper.find('.next').text()).toContain('Next, you')
    expect(wrapper.find('.next').text()).toContain('You pass')
  })

  it('continues, plays on its own and starts again', async () => {
    const wrapper = show(replay())

    await wrapper.find('.continue').trigger('click')
    await wrapper.find('[aria-pressed]').trigger('click')
    await wrapper.find('.restart').trigger('click')

    expect(Object.keys(wrapper.emitted())).toEqual(expect.arrayContaining(['proceed', 'autoplay', 'restart']))
  })

  it('does not continue while it plays on its own', () => {
    const wrapper = show(replay(), { autoplay: true })

    expect(wrapper.find('.continue').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[aria-pressed]').text()).toBe('Pause')
  })

  it('tells the result once every message is replayed', () => {
    const wrapper = show(replay({ version: 3, next: null, ended: true }))

    expect(wrapper.find('.result').text()).toContain('Victory')
    expect(wrapper.find('.result').text()).toContain('The opponent conceded.')
    expect(wrapper.find('.continue').exists()).toBe(false)
  })

  it('tells why the game can no longer be replayed', () => {
    const wrapper = show(replay(), { diverged: true })

    expect(wrapper.find('[role="alert"]').text()).toContain('cannot be replayed beyond message 2')
    expect(wrapper.find('.continue').exists()).toBe(false)
  })

  it('tells where a jump goes while it is replayed', () => {
    const wrapper = show(replay(), { jumping: 1, busy: true })

    expect(wrapper.find('[role="status"]').text()).toBe('Replaying the game up to message 1…')
  })
})

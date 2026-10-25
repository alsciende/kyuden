<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import type { ConflictType, Element } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import CardDialog from '@/components/game/CardDialog.vue'
import CardPreview from '@/components/game/CardPreview.vue'
import GameBoard from '@/components/game/GameBoard.vue'
import GameSidebar from '@/components/game/GameSidebar.vue'
import LogDrawer from '@/components/game/LogDrawer.vue'
import PlayerDialog from '@/components/game/PlayerDialog.vue'
import TooltipLayer from '@/components/game/TooltipLayer.vue'
import { gameEnded, victoryText } from '@/lib/board'
import { cardMenu } from '@/lib/cardActions'
import { hidePreview } from '@/lib/preview'
import { isGuest, session } from '@/lib/session'
import { emptySelection, toggle } from '@/lib/selection'
import { hideTooltip } from '@/lib/tooltip'
import { useGame } from '@/lib/useGame'

/**
 * A game, as its player plays it: the board on the left, the bar of the step and of the state of the game on the
 * right. It follows the game through the Mercure hub. Once the game has ended, the board darkens under the result.
 */
const route = useRoute()
const { game, log, cards, loadError, error, sending, send, refresh } = useGame(String(route.params.id))

const SIDEBAR_WIDTH = 340

const selection = reactive(emptySelection())
const openCardId = ref<string | null>(null)
const openPlayerId = ref<string | null>(null)
const logOpen = ref(false)
const boardRight = ref(window.innerWidth - SIDEBAR_WIDTH)

// a new step starts a new selection
watch(
  () => game.value?.place,
  () => Object.assign(selection, emptySelection()),
)

const menu = computed(() =>
  game.value === null || openCardId.value === null ? undefined : cardMenu(game.value, cards, openCardId.value),
)

// a card that has left the view (shuffled, discarded from a hidden zone) closes its dialog
watch(menu, (current) => {
  if (current === undefined) {
    openCardId.value = null
  }
})

const openPlayer = computed(() => game.value?.players.find((player) => player.id === openPlayerId.value))

function open(id: string): void {
  hidePreview()
  error.value = null
  openCardId.value = id
}

function reveal(type: 'reveal_dynasty_card' | 'reveal_province', id: string): void {
  if (!sending.value) {
    void send(type, { card_id: id })
  }
}

function switchType(): void {
  if (!sending.value) {
    void send('switch_conflict_type')
  }
}

function switchRing(element: Element): void {
  if (!sending.value) {
    void send('switch_contested_ring', { ring: element })
  }
}

const ended = computed(() => game.value !== null && gameEnded(game.value))
const result = computed(() =>
  game.value === null
    ? null
    : { victory: game.value.winner_id === game.value.viewer_id, text: victoryText(game.value) },
)

const guest = computed(() => (isGuest(session.value) ? session.value : null))

function chooseRing(element: Element): void {
  selection.ring = selection.ring === element ? null : element
}

function chooseType(type: ConflictType): void {
  selection.type = selection.type === type ? null : type
}

function chooseProvince(id: string): void {
  selection.provinceId = selection.provinceId === id ? null : id
}

function onResize(): void {
  boardRight.value = window.innerWidth - SIDEBAR_WIDTH
}

onMounted(() => {
  void refresh()
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  hidePreview()
  hideTooltip()
})

const problem = computed(() => {
  const current = loadError.value
  if (current === null) {
    return null
  }
  switch (current.status) {
    case 401:
      return 'This game is played from another browser.'
    case 403:
      return 'You are not a player of this game.'
    case 404:
      return 'There is no such game.'
    default:
      return current.detail
  }
})
</script>

<template>
  <div v-if="game" class="table">
    <GameBoard
      :game="game"
      :cards="cards"
      :selection="selection"
      @open="open"
      @ring="chooseRing"
      @type="chooseType"
      @province="chooseProvince"
      @attacker="selection.attackerIds = toggle(selection.attackerIds, $event)"
      @defender="selection.defenderIds = toggle(selection.defenderIds, $event)"
      @reveal="reveal"
      @switch-type="switchType"
      @switch-ring="switchRing"
    />
    <GameSidebar
      :game="game"
      :cards="cards"
      :log="log"
      :selection="selection"
      :sending="sending"
      :error="openCardId || openPlayerId ? null : error"
      :send="send"
      @open-log="logOpen = true"
      @open-player="openPlayerId = $event"
    />

    <div v-if="ended && result" class="ended" role="dialog" aria-labelledby="game-result">
      <div class="ended-content">
        <ClanMon :src="enso" :size="96" color="var(--paper)" />
        <h1 id="game-result">{{ result.victory ? 'Victory' : 'Defeat' }}</h1>
        <p v-if="result.text">{{ result.text }}</p>
        <!-- a guest is invited to keep their name: the introductory game is over -->
        <div v-if="guest" class="invitation">
          <p class="invitation-title">Stay at court, {{ guest.name }}</p>
          <p>Create an account to keep your name and this game, and to play again from any browser.</p>
          <div class="invitation-actions">
            <RouterLink :to="{ name: 'signup' }" class="btn leave">Create an account</RouterLink>
            <RouterLink to="/" class="btn-link">Leave the game</RouterLink>
          </div>
        </div>
        <RouterLink v-else to="/" class="btn leave">Leave the game</RouterLink>
      </div>
    </div>

    <CardPreview :cards="cards" :board-right="boardRight" />
    <TooltipLayer />

    <CardDialog
      v-if="menu"
      :key="menu.located.card.id"
      :menu="menu"
      :game="game"
      :cards="cards"
      :sending="sending"
      :error="error"
      :send="send"
      @close="openCardId = null"
    />
    <PlayerDialog
      v-if="openPlayer"
      :game="game"
      :player="openPlayer"
      :sending="sending"
      :error="error"
      :send="send"
      @close="openPlayerId = null"
    />
    <LogDrawer v-if="logOpen" :game="game" :cards="cards" :log="log" @close="logOpen = false" />
  </div>

  <div v-else class="waiting screen">
    <ClanMon :src="enso" :size="120" color="var(--paper)" />
    <template v-if="problem">
      <h1>{{ problem }}</h1>
      <RouterLink to="/" class="btn back">Back to the start</RouterLink>
    </template>
    <p v-else class="soon">The table is being set</p>
  </div>
</template>

<style scoped>
.table {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  height: 100vh;
  overflow: hidden;
  background: var(--board);
}

.ended {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  right: 340px;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(31, 27, 22, 0.78);
  color: var(--paper);
  text-align: center;
}

.ended-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
}

.ended h1 {
  font-size: 72px;
  line-height: 1;
}

.ended p {
  font-size: 17px;
  color: #d8cdb8;
}

.invitation {
  margin-top: 22px;
  padding-top: 26px;
  border-top: 1px solid rgba(244, 238, 226, 0.25);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  max-width: 560px;
}

.ended .invitation-title {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
  color: var(--paper);
}

.ended p {
  line-height: 1.55;
}

.invitation-actions {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 10px;
}

.invitation-actions .leave {
  margin-top: 0;
}

.invitation-actions .btn-link {
  color: var(--paper);
}

.leave {
  margin-top: 14px;
  background: var(--seal);
  border-color: var(--seal);
  color: var(--seal-ink);
}

.waiting {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 22px;
  padding: 48px;
  text-align: center;
  background: var(--ink);
  color: var(--paper);
}

h1 {
  font-size: 40px;
}

.soon {
  font-size: 14px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #a89c88;
}

.back {
  margin-top: 12px;
  background: transparent;
  color: var(--paper);
  border-color: var(--paper);
}

.back:hover {
  background: var(--paper);
  color: var(--ink);
}
</style>

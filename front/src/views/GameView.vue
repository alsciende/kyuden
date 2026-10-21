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
import { cardMenu } from '@/lib/cardActions'
import { hidePreview } from '@/lib/preview'
import { emptySelection, toggle } from '@/lib/selection'
import { useGame } from '@/lib/useGame'

/**
 * A game, as its player plays it: the board on the left, the bar of the step and of the state of the game on the
 * right. It follows the game through the Mercure hub.
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

    <CardPreview :cards="cards" :board-right="boardRight" />

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

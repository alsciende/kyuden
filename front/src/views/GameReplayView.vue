<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import type { Send } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import CardDialog from '@/components/game/CardDialog.vue'
import CardPreview from '@/components/game/CardPreview.vue'
import GameBoard from '@/components/game/GameBoard.vue'
import GameSidebar from '@/components/game/GameSidebar.vue'
import PlayerBar from '@/components/game/PlayerBar.vue'
import ReplayLog from '@/components/game/ReplayLog.vue'
import ReplayPanel from '@/components/game/ReplayPanel.vue'
import TooltipLayer from '@/components/game/TooltipLayer.vue'
import { cardsUnder, opponent, viewer } from '@/lib/board'
import { cardMenu } from '@/lib/cardActions'
import { hidePreview } from '@/lib/preview'
import { replayLogRows } from '@/lib/replay'
import { RESOLVING_CARDS, resolvingCardIds } from '@/lib/resolutions'
import { emptySelection } from '@/lib/selection'
import { SPECTATING } from '@/lib/spectating'
import { hideTooltip } from '@/lib/tooltip'
import { CARDS_UNDER } from '@/lib/underneath'
import { useReplay } from '@/lib/useReplay'

/**
 * The replay of a game the player played: the board of its copy as they saw it then, read only, stopped before each of
 * their actions; the panel of the replay in place of what the player does, the log of the whole game in place of its
 * last lines. A card opens to be looked at; nothing is sent to the game.
 */
const route = useRoute()
const gameId = String(route.params.id)
const {
  replay,
  game,
  log,
  cards,
  stepFrom,
  loadError,
  error,
  diverged,
  busy,
  jumping,
  autoplay,
  load,
  proceed,
  restart,
  jump,
  toggleAutoplay,
} = useReplay(gameId)

// the players are "You" and "Opp.", as when they played
provide(
  SPECTATING,
  computed(() => false),
)
provide(
  CARDS_UNDER,
  computed(() => (game.value === null ? new Map() : cardsUnder(game.value))),
)
provide(
  RESOLVING_CARDS,
  computed(() => (game.value === null ? new Set<string>() : resolvingCardIds(game.value, cards))),
)

/** a replay sends nothing */
const send: Send = () => Promise.resolve(false)

const SIDEBAR_WIDTH = 340
const selection = reactive(emptySelection())
const openCardId = ref<string | null>(null)
const boardRight = ref(window.innerWidth - SIDEBAR_WIDTH)

const me = computed(() => (game.value === null ? undefined : viewer(game.value)))
const opp = computed(() => (game.value === null ? undefined : opponent(game.value)))
const rows = computed(() => (replay.value === null ? [] : replayLogRows(log.value, replay.value)))

const menu = computed(() =>
  game.value === null || openCardId.value === null ? undefined : cardMenu(game.value, cards, openCardId.value),
)
watch(menu, (current) => {
  if (current === undefined) {
    openCardId.value = null
  }
})

function open(id: string): void {
  hidePreview()
  openCardId.value = id
}

function onResize(): void {
  boardRight.value = window.innerWidth - SIDEBAR_WIDTH
}

onMounted(() => {
  void load()
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
  switch (current.type) {
    case 'no_session':
      return 'Sign in to replay your games.'
    case 'not_a_player':
      return 'Only the players of a game replay it.'
    case 'game_not_ended':
      return 'This game is not over yet.'
    case 'game_not_found':
      return 'There is no such game.'
    default:
      return current.detail
  }
})
</script>

<template>
  <div v-if="game && replay" class="table">
    <PlayerBar
      v-if="opp"
      :game="game"
      :player="opp"
      :cards="cards"
      position="top"
      :sending="busy"
      :error="null"
      :send="send"
      class="top-bar"
      @open="open"
    />
    <GameBoard class="middle" :game="game" :cards="cards" :selection="selection" @open="open" />
    <GameSidebar
      class="middle"
      :game="game"
      :cards="cards"
      :log="[]"
      :spectators="[]"
      :user-id="null"
      :selection="selection"
      :sending="busy"
      :error="null"
      :send="send"
    >
      <template #panel>
        <ReplayPanel
          :replay="replay"
          :game="game"
          :cards="cards"
          :log="log"
          :step-from="stepFrom"
          :busy="busy"
          :jumping="jumping"
          :autoplay="autoplay"
          :diverged="diverged"
          :error="error"
          @proceed="proceed"
          @autoplay="toggleAutoplay"
          @restart="restart"
        />
      </template>
      <template #log>
        <ReplayLog
          :rows="rows"
          :game="game"
          :cards="cards"
          :version="replay.version"
          :busy="busy || diverged"
          @jump="jump"
        />
      </template>
    </GameSidebar>
    <PlayerBar
      v-if="me"
      :game="game"
      :player="me"
      :cards="cards"
      position="bottom"
      :sending="busy"
      :error="null"
      :send="send"
      class="bottom-bar"
      @open="open"
    />

    <CardPreview :cards="cards" :board-right="boardRight" />
    <TooltipLayer />

    <CardDialog
      v-if="menu"
      :key="menu.located.card.id"
      :menu="menu"
      :game="game"
      :cards="cards"
      :sending="busy"
      :error="null"
      :send="send"
      @close="openCardId = null"
    />
  </div>

  <div v-else class="waiting screen">
    <ClanMon :src="enso" :size="120" color="var(--paper)" />
    <template v-if="problem">
      <h1>{{ problem }}</h1>
      <RouterLink :to="{ name: 'history' }" class="btn back">Back to your games</RouterLink>
    </template>
    <p v-else class="soon">The game is being replayed</p>
  </div>
</template>

<style scoped>
.table {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  grid-template-rows: auto minmax(0, 1fr) auto;
  height: 100vh;
  /* the visible height: Safari on iPad counts its toolbars in 100vh */
  height: 100dvh;
  overflow: hidden;
  background: var(--board);
}

.top-bar {
  grid-row: 1;
}

.middle {
  grid-row: 2;
  min-height: 0;
}

.bottom-bar {
  grid-row: 3;
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

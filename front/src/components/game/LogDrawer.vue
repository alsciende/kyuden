<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, useTemplateRef } from 'vue'

import type { GameView, LoggedMessage } from '@/api/game'
import LogLine from '@/components/game/LogLine.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import type { Cards } from '@/lib/board'
import { isModalOpen } from '@/lib/topLayer'

/**
 * The whole log of the game, over the sidebar: each message handled, by whom, and what it changed. Not modal: the
 * board stays in use beside it.
 */
const props = defineProps<{ game: GameView; cards: Cards; log: LoggedMessage[] }>()
const emit = defineEmits<{ close: [] }>()

const drawer = useTemplateRef<HTMLDialogElement>('drawer')
const list = useTemplateRef<HTMLElement>('list')
const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')

const messages = computed(() =>
  props.log.map((message) => ({
    version: message.version,
    player: props.game.players.find((player) => player.id === message.player_id),
    type: message.type.replaceAll('_', ' '),
    entries: message.entries,
  })),
)

function onKey(event: KeyboardEvent): void {
  // a window open over the page takes the key
  if (event.key === 'Escape' && !isModalOpen()) {
    emit('close')
  }
}

onMounted(async () => {
  window.addEventListener('keydown', onKey)
  if (typeof drawer.value?.show === 'function') {
    drawer.value.show()
  } else {
    drawer.value?.setAttribute('open', '')
  }
  closeButton.value?.focus()
  await nextTick()
  list.value?.scrollTo({ top: list.value.scrollHeight })
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <dialog ref="drawer" class="drawer" aria-label="Log of the game">
    <header class="head">
      <h2>Log</h2>
      <button ref="closeButton" type="button" class="btn btn-ghost" @click="emit('close')">Close</button>
    </header>
    <ol ref="list" class="messages">
      <li v-for="message in messages" :key="message.version" class="message">
        <div class="meta">
          <PlayerChip v-if="message.player" :player="message.player" :viewer-id="game.viewer_id" short />
          <span class="type">{{ message.type }}</span>
        </div>
        <LogLine v-for="(entry, index) in message.entries" :key="index" :entry="entry" :game="game" :cards="cards" />
      </li>
    </ol>
  </dialog>
</template>

<style scoped>
.drawer {
  position: fixed;
  inset: 0 0 0 auto;
  width: 420px;
  height: auto;
  margin: 0;
  padding: 0;
  z-index: 30;
  background: var(--paper-2);
  color: var(--ink);
  border: 0;
  border-left: 1px solid var(--ink);
  box-shadow: -18px 0 40px rgba(31, 27, 22, 0.18);
  flex-direction: column;
}

.drawer[open] {
  display: flex;
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--line);
}

.head .btn {
  min-height: 40px;
  padding: 0 16px;
  font-size: 14px;
}

.messages {
  list-style: none;
  margin: 0;
  padding: 12px 24px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.message {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  line-height: 1.4;
  color: var(--ink-2);
}

.meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.type {
  font-weight: 700;
  color: var(--ink-3);
  text-transform: capitalize;
}
</style>

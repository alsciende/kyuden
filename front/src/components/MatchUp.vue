<script setup lang="ts">
import type { WatchableGameRow } from '@/lib/watch'

/**
 * The two players of a game, on the tints of their clans.
 */
defineProps<{ players: WatchableGameRow['players'] }>()
</script>

<template>
  <span class="match-up">
    <template v-for="(player, index) in players" :key="index">
      <span v-if="index > 0" class="versus">vs</span>
      <span class="who" :style="{ background: player.tint }">{{ player.name }}</span>
    </template>
  </span>
</template>

<style scoped>
.match-up {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

/* the tints are light whatever the theme: the names keep the ink of the day */
.who {
  padding: 1px 7px;
  border-radius: 2px;
  color: #1f1b16;
  font-family: var(--font-display);
  font-size: 17px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.versus {
  font-size: 14px;
  color: var(--ink-3);
}
</style>

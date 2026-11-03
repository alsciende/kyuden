<script setup lang="ts">
import { computed } from 'vue'

import type { PlayerView } from '@/api/game'
import { findClan } from '@/data/clans'
import { useSpectating } from '@/lib/spectating'

/**
 * A player named from the point of view of the viewer ("You", "Opponent"), on the tint of their clan: names may be
 * long, and both players may be of the same clan. The tints are light whatever the theme: the chip keeps the ink of
 * the day. A spectator reads the names of the players.
 */
const props = withDefaults(defineProps<{ player: PlayerView | undefined; viewerId: string; short?: boolean }>(), {
  short: false,
})

const spectating = useSpectating()
const mine = computed(() => props.player?.id === props.viewerId)
const label = computed(() => {
  if (spectating.value) {
    return props.player?.name ?? ''
  }

  return mine.value ? 'You' : props.short ? 'Opp.' : 'Opponent'
})
const background = computed(() => findClan(props.player?.clan ?? null)?.tint ?? '#e2d8c4')
</script>

<template>
  <span class="chip" :style="{ background }">{{ label }}</span>
</template>

<style scoped>
.chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 2px;
  color: #1f1b16;
  font-weight: 700;
  white-space: nowrap;
}
</style>

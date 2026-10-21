<script setup lang="ts">
import { computed } from 'vue'

import type { PlayerView } from '@/api/game'
import { findClan } from '@/data/clans'

/**
 * A player named from the point of view of the viewer ("You", "Opponent"), on the tint of their clan: names may be
 * long, and both players may be of the same clan.
 */
const props = withDefaults(defineProps<{ player: PlayerView | undefined; viewerId: string; short?: boolean }>(), {
  short: false,
})

const mine = computed(() => props.player?.id === props.viewerId)
const background = computed(() => findClan(props.player?.clan ?? null)?.tint ?? 'var(--paper-3)')
</script>

<template>
  <span class="chip" :style="{ background }">{{ mine ? 'You' : short ? 'Opp.' : 'Opponent' }}</span>
</template>

<style scoped>
.chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 2px;
  color: var(--ink);
  font-weight: 700;
  white-space: nowrap;
}
</style>

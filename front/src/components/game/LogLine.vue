<script setup lang="ts">
import { computed } from 'vue'

import type { GameView, LogEntry } from '@/api/game'
import CardName from '@/components/game/CardName.vue'
import type { Cards } from '@/lib/board'
import { entryParts } from '@/lib/logText'

/**
 * A line of the log: the names of the cards show their image when hovered.
 */
const props = defineProps<{ entry: LogEntry; game: GameView; cards: Cards }>()

const parts = computed(() => entryParts(props.entry, props.game, props.cards))
</script>

<template>
  <p>
    <template v-for="(part, index) in parts" :key="index"
      ><CardName v-if="part.code" :code="part.code">{{ part.text }}</CardName
      ><template v-else>{{ part.text }}</template></template
    >
  </p>
</template>

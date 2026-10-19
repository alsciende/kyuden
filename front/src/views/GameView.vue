<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { findClan, findClanByDeck } from '@/data/clans'
import { newcomer } from '@/lib/newcomer'

/**
 * The game. The board comes next: for now, the page tells who faces whom.
 */
const route = useRoute()

const game = computed(() => (newcomer.game?.game_id === route.params.id ? newcomer.game : null))
const clan = computed(() => findClan(newcomer.clanId))
const opponentClan = computed(() => (game.value ? findClanByDeck(game.value.opponent.deck_id) : undefined))
</script>

<template>
  <div class="table screen">
    <ClanMon :src="enso" :size="180" color="var(--paper)" />
    <h1>The table is set.</h1>
    <p v-if="game && clan && opponentClan">
      {{ newcomer.name }} of the {{ clan.name }} Clan faces {{ game.opponent.name }} of the
      {{ opponentClan.name }} Clan.
    </p>
    <p class="soon">The game board is coming soon</p>
    <RouterLink to="/" class="btn back">Back to the start</RouterLink>
  </div>
</template>

<style scoped>
.table {
  flex: 1;
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
  font-size: 48px;
}

p {
  font-size: 17px;
  color: var(--line);
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

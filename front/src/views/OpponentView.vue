<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { ApiProblem } from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import StepTrail from '@/components/StepTrail.vue'
import { CLANS, findClan, findClanByDeck } from '@/data/clans'
import { newcomer } from '@/lib/newcomer'
import { startIntroductoryGame } from '@/lib/session'

/** the search lasts at least this long, so that the visitor sees it happen */
const MINIMUM_SEARCH_MS = 1800

const router = useRouter()

// the route requires a clan and a name
const clan = computed(() => findClan(newcomer.clanId) ?? CLANS[0]!)
const name = computed(() => newcomer.name ?? '')

const error = ref<string | null>(null)
const game = computed(() => newcomer.game)
const opponentClan = computed(() => (game.value ? findClanByDeck(game.value.opponent.deck_id) : undefined))

async function search(): Promise<void> {
  error.value = null
  try {
    const [created] = await Promise.all([
      startIntroductoryGame(name.value, clan.value),
      new Promise((resolve) => setTimeout(resolve, MINIMUM_SEARCH_MS)),
    ])
    newcomer.game = created
  } catch (exception) {
    error.value =
      exception instanceof ApiProblem && exception.type === 'bot_unavailable'
        ? 'No opponent is available right now. Please try again in a moment.'
        : 'The game could not be created. Please try again.'
  }
}

onMounted(() => {
  if (newcomer.game === null) {
    void search()
  }
})

function begin(): void {
  if (game.value) {
    void router.push({ name: 'game', params: { id: game.value.game_id } })
  }
}
</script>

<template>
  <StepTrail :current="2" />
  <div class="matching screen">
    <h1 aria-live="polite">
      {{ game ? 'Your opponent is ready' : error ? 'No opponent found' : 'Finding an opponent…' }}
    </h1>

    <div class="versus">
      <div class="side">
        <ClanMon :src="clan.mon" :color="clan.color" :size="220" :label="`${clan.name} Clan mon`" />
        <div class="side-name">{{ name }}</div>
        <div class="side-meta">{{ clan.name }} Clan · <strong>You</strong></div>
      </div>

      <span class="seal" aria-hidden="true">対</span>

      <div class="side">
        <template v-if="game">
          <ClanMon
            class="screen"
            :src="opponentClan?.mon ?? enso"
            :color="opponentClan?.color"
            :size="220"
            :label="opponentClan ? `${opponentClan.name} Clan mon` : undefined"
          />
          <div class="side-name">{{ game.opponent.name }}</div>
          <div class="side-meta">
            <template v-if="opponentClan">{{ opponentClan.name }} Clan · </template>
            <span class="badge">BOT</span>
          </div>
        </template>
        <template v-else>
          <span class="spinner" :class="{ 'is-stopped': error }">
            <ClanMon :src="enso" :size="220" color="var(--ink-3)" />
          </span>
          <div class="side-name muted">…</div>
          <div class="side-meta">{{ error ?? 'Summoning a worthy opponent' }}</div>
        </template>
      </div>
    </div>

    <section aria-label="What happens next" class="next">
      <div>
        <strong>Setup</strong>
        <span>Choose the province under your stronghold. Your provinces fill up and you draw your first cards.</span>
      </div>
      <div>
        <strong>The round</strong>
        <span>Dynasty, Draw, Conflict and Fate. The current phase and step are always on screen.</span>
      </div>
      <div>
        <strong>Your turn to act</strong>
        <span>Kyūden tells you when you can act and lists what you can do, with a word on each choice.</span>
      </div>
    </section>

    <div class="actions">
      <button v-if="error" type="button" class="btn btn-primary btn-large" @click="search">Try again</button>
      <button v-else type="button" class="btn btn-primary btn-large begin" :disabled="!game" @click="begin">
        Begin the game
      </button>
      <RouterLink to="/" class="btn-link">Change clan</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.matching {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 28px 96px 36px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
}

h1 {
  text-align: center;
  font-size: 44px;
}

.versus {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 48px;
}

.side {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.side-name {
  font-family: var(--font-display);
  font-size: 34px;
  font-weight: 700;
}

.side-name.muted {
  color: var(--ink-3);
}

.side-meta {
  font-size: 15px;
  color: var(--ink-2);
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 8px;
}

.side-meta strong {
  color: var(--ink);
}

.badge {
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  padding: 1px 7px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--ink);
}

.seal {
  width: 84px;
  height: 84px;
  background: var(--seal);
  color: var(--seal-ink);
  font-family: var(--font-display);
  font-size: 50px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transform: rotate(-3deg);
}

.spinner {
  display: inline-flex;
  animation: spin 9s linear infinite;
}

.spinner.is-stopped {
  animation: none;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .spinner {
    animation: none;
  }
}

.next {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
  padding-top: 26px;
  border-top: 1px solid var(--line);
}

.next div {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.next strong {
  font-size: 16px;
}

.next span {
  font-size: 15px;
  line-height: 1.55;
  color: var(--ink-2);
}

.actions {
  display: flex;
  justify-content: center;
  gap: 14px;
  align-items: center;
}

.begin {
  padding: 0 34px;
}
</style>

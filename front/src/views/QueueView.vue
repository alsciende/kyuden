<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import {
  ApiProblem,
  fetchCard,
  fetchDecks,
  fetchQueue,
  fetchSelfGames,
  joinQueue,
  leaveQueue,
  type Card,
} from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { CLANS } from '@/data/clans'
import { follow } from '@/lib/hub'
import { inverted } from '@/lib/page'
import { deckChoices, waitingTime, type DeckChoice, type DeckChoices } from '@/lib/queue'
import { session } from '@/lib/session'

/**
 * The matchmaking queue of the players who have an account: they choose a deck and join the queue, then wait (the
 * page in reverse) until the hub tells them a game of theirs has started (`game_started` on their topic), which opens.
 */
const router = useRouter()

/** null while the queue is read */
const state = ref<'choosing' | 'waiting' | null>(null)
const choices = ref<DeckChoices>({ mine: [], starters: [], preselected: null })
const chosen = ref<string | null>(null)
/** the deck the user waits with */
const waitingWith = ref<string | null>(null)
const since = ref(Date.now())
const now = ref(Date.now())
const error = ref<string | null>(null)
const sending = ref(false)
/** the strongholds of the clans, by code, read from the API */
const strongholds = reactive<Record<string, Card>>({})

let unfollow: (() => void) | null = null
let ticking: ReturnType<typeof setInterval> | null = null

const allChoices = computed(() => [...choices.value.mine, ...choices.value.starters])
const chosenDeck = computed(() => allChoices.value.find((choice) => choice.deck.id === chosen.value))
const waitingDeck = computed(() => allChoices.value.find((choice) => choice.deck.id === waitingWith.value))

watch(state, (current) => (inverted.value = current === 'waiting'))

onMounted(async () => {
  const token = session.value?.token
  if (!token) {
    return
  }
  for (const clan of CLANS) {
    fetchCard(clan.strongholdCode)
      .then((card) => (strongholds[clan.strongholdCode] = card))
      .catch(() => {
        // the deck is shown without its stronghold
      })
  }
  unfollow = follow(
    token,
    (subscription) => [subscription.topic],
    (data) => {
      if (data.event === 'game_started' && typeof data.id === 'string' && state.value === 'waiting') {
        void open(data.id)
      }
    },
  )
  ticking = setInterval(() => (now.value = Date.now()), 1000)

  try {
    const decks = await fetchDecks(token)
    choices.value = deckChoices(decks, session.value?.preferredClan ?? null)
    chosen.value = choices.value.preselected
    await readQueue(token)
  } catch (problem) {
    if (!(problem instanceof ApiProblem)) {
      throw problem
    }
    error.value = problem.detail
    state.value = 'choosing'
  }
})

onBeforeUnmount(() => {
  unfollow?.()
  if (ticking !== null) {
    clearInterval(ticking)
  }
  inverted.value = false
})

/**
 * Whether the user waits in the queue; once they have joined (`joined`), not waiting means the queue has matched
 * them already: their last game opens.
 */
async function readQueue(token: string, joined = false): Promise<void> {
  try {
    waitingWith.value = (await fetchQueue(token)).deck_id
    if (state.value !== 'waiting') {
      since.value = Date.now()
    }
    state.value = 'waiting'
  } catch (problem) {
    if (!(problem instanceof ApiProblem) || problem.status !== 404) {
      throw problem
    }
    if (joined) {
      const games = await fetchSelfGames(token)
      if (games[0]) {
        await open(games[0].id)

        return
      }
    }
    state.value = 'choosing'
  }
}

async function join(): Promise<void> {
  const token = session.value?.token
  if (!token || chosen.value === null || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await joinQueue(token, chosen.value)
  } catch (problem) {
    // already in the queue (another tab): wait with that deck
    if (!(problem instanceof ApiProblem) || problem.type !== 'already_queued') {
      error.value = problem instanceof ApiProblem ? problem.detail : 'The queue cannot be joined.'
      sending.value = false

      return
    }
  }
  try {
    // matched at once when another player waited, maybe before the hub could tell
    await readQueue(token, true)
  } catch {
    error.value = 'The queue cannot be read.'
  } finally {
    sending.value = false
  }
}

async function leave(): Promise<void> {
  const token = session.value?.token
  if (!token || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await leaveQueue(token)
    state.value = 'choosing'
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.status === 404) {
      // matched meanwhile: the game opens, or it is the last one
      await readQueue(token, true)
    } else {
      error.value = 'The queue cannot be left.'
    }
  } finally {
    sending.value = false
  }
}

async function open(gameId: string): Promise<void> {
  state.value = null
  await router.push({ name: 'game', params: { id: gameId } })
}

function stronghold(choice: DeckChoice): string | undefined {
  return choice.clan ? strongholds[choice.clan.strongholdCode]?.name : undefined
}
</script>

<template>
  <div v-if="state === 'choosing'" class="queue screen">
    <div class="heading">
      <div class="eyebrow">Play a game</div>
      <h1>Choose your deck</h1>
      <p class="lead">You face the next player who joins the queue.</p>
    </div>

    <section v-if="choices.mine.length > 0" class="decks mine" aria-labelledby="mine">
      <h2 id="mine" class="form-label">Your decks</h2>
      <div class="grid">
        <button
          v-for="choice in choices.mine"
          :key="choice.deck.id"
          type="button"
          class="deck"
          :aria-pressed="chosen === choice.deck.id"
          @click="chosen = choice.deck.id"
        >
          <span class="kanji" :style="{ color: choice.clan?.color }">{{ choice.clan?.kanji ?? '' }}</span>
          <span class="name">{{ choice.name }}</span>
          <span class="detail">{{ [choice.clan?.name, stronghold(choice)].filter(Boolean).join(' · ') }}</span>
        </button>
      </div>
    </section>

    <section class="decks" aria-labelledby="starters">
      <h2 id="starters" class="form-label">Starter decks</h2>
      <div class="grid">
        <button
          v-for="choice in choices.starters"
          :key="choice.deck.id"
          type="button"
          class="deck"
          :aria-pressed="chosen === choice.deck.id"
          @click="chosen = choice.deck.id"
        >
          <span class="kanji" :style="{ color: choice.clan?.color }">{{ choice.clan?.kanji ?? '' }}</span>
          <span class="name">{{ choice.name }}</span>
          <span class="detail">{{ stronghold(choice) }}</span>
        </button>
      </div>
    </section>

    <div class="actions">
      <button type="button" class="btn btn-primary btn-large" :disabled="!chosenDeck || sending" @click="join">
        Join the queue
      </button>
      <span v-if="chosenDeck" class="with">
        with <strong>{{ chosenDeck.name }}</strong>
      </span>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    </div>
  </div>

  <div v-else-if="state === 'waiting'" class="waiting screen">
    <ClanMon :src="enso" :size="168" class="enso" />
    <div class="status" role="status">
      <div class="eyebrow">In the queue · {{ waitingTime((now - since) / 1000) }}</div>
      <h1>Waiting for an opponent</h1>
      <p class="lead">
        <template v-if="waitingDeck">
          You play the <strong>{{ waitingDeck.name }}</strong
          >{{ waitingDeck.deck.owner_id === null ? ' starter deck' : '' }}.
        </template>
        The game opens as soon as another player joins.
      </p>
    </div>
    <button type="button" class="btn btn-ghost" :disabled="sending" @click="leave">Leave the queue</button>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.queue {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 30px;
  padding: 44px 96px 40px;
}

.heading {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

h1 {
  font-size: 64px;
  line-height: 1;
}

.lead {
  font-size: 16px;
  color: var(--ink-2);
}

.lead strong,
.with strong {
  font-family: var(--font-display);
  font-size: 18px;
}

.decks {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.decks.mine {
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

h2.form-label {
  font-family: var(--font-ui);
}

.grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 14px;
}

.deck {
  font: inherit;
  color: var(--ink);
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px 16px 14px;
  min-height: 132px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
  cursor: pointer;
}

.deck:hover {
  border-color: var(--ink);
}

.deck[aria-pressed='true'] {
  border: 2px solid var(--seal);
  background: var(--selected);
}

.kanji {
  font-family: 'Shippori Mincho', serif;
  font-weight: 700;
  font-size: 30px;
  line-height: 1;
  min-height: 30px;
}

.name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 20px;
  line-height: 1.15;
  overflow-wrap: anywhere;
}

.detail {
  font-size: 13px;
  color: var(--ink-3);
}

.actions {
  margin-top: auto;
  padding-top: 24px;
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  gap: 24px;
}

.with {
  font-size: 15px;
  color: var(--ink-2);
}

.waiting {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 28px;
  padding: 40px 96px 72px;
  text-align: center;
}

.status {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.waiting .lead {
  max-width: 560px;
}

.enso {
  animation: turn 3.2s linear infinite;
}

.waiting .btn-ghost:hover {
  color: var(--seal);
  border-color: var(--seal);
  background: transparent;
}

@keyframes turn {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .enso {
    animation-duration: 12s;
  }
}
</style>

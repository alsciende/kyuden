<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import {
  ApiProblem,
  createBotGame,
  deleteDeck,
  fetchCard,
  fetchDecks,
  fetchInvitationStatus,
  fetchQueue,
  fetchSelfGames,
  fetchSelfInvitation,
  importDeck,
  joinQueue,
  leaveQueue,
  type Card,
  type Invitation,
  type QueueInvitation,
} from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ActionIcon from '@/components/ActionIcon.vue'
import ClanMon from '@/components/ClanMon.vue'
import { CLANS } from '@/data/clans'
import { HOUSE_BOT } from '@/data/houseBot'
import { follow } from '@/lib/hub'
import { timeLeft } from '@/lib/invitation'
import { newcomer } from '@/lib/newcomer'
import { inverted } from '@/lib/page'
import {
  deckChoice,
  deckChoices,
  importProblem,
  waitingTime,
  withImported,
  withoutDeck,
  type DeckChoice,
  type DeckChoices,
  type ImportProblem,
} from '@/lib/queue'
import { session } from '@/lib/session'

/**
 * The matchmaking queue of the players who have an account: they choose a deck and join the queue, then wait (the
 * page in reverse) until the hub tells them a game of theirs has started (`game_started` on their topic), which opens.
 * A deck of EmeraldDB is imported from the URL of its page, then chosen: the player still joins the queue themselves.
 * Their own decks are deleted from here, once confirmed in the tile of the deck.
 *
 * Instead of the queue, the user may face the house bot with the deck chosen: the game opens at once.
 *
 * A friendly game: the host of an invitation comes with its id (`?invitation=`), its guest with its code (`?code=`,
 * a guest account too); they are matched together only. The host leaving the queue cancels the invitation.
 */
const router = useRouter()
const route = useRoute()

/** the invitation of the host, or the code of the guest and who invites */
const friendly = ref<{ host: Invitation } | { code: string; hostName: string } | null>(null)
const invitationQuery = typeof route.query.invitation === 'string' ? route.query.invitation : null
const codeQuery = typeof route.query.code === 'string' ? route.query.code : null

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
/** the URL of a deck of EmeraldDB to import */
const deckUrl = ref('')
const importing = ref(false)
const importFailure = ref<ImportProblem | null>(null)
/** what happened to a deck of the user: imported, already theirs, deleted */
const notice = ref<{ before: string; name: string; after: string } | null>(null)
/** the deck of the user whose deletion is asked to be confirmed */
const confirmingDeletion = ref<string | null>(null)
const deleting = ref(false)
/** the strongholds of the clans, by code, read from the API */
const strongholds = reactive<Record<string, Card>>({})

let unfollow: (() => void) | null = null
let ticking: ReturnType<typeof setInterval> | null = null

const allChoices = computed(() => [...choices.value.mine, ...choices.value.starters])
const chosenDeck = computed(() => allChoices.value.find((choice) => choice.deck.id === chosen.value))
const waitingDeck = computed(() => allChoices.value.find((choice) => choice.deck.id === waitingWith.value))

const hostInvitation = computed(() =>
  friendly.value !== null && 'host' in friendly.value ? friendly.value.host : null,
)
const invitedBy = computed(() =>
  friendly.value !== null && 'hostName' in friendly.value ? friendly.value.hostName : null,
)
/** who the user faces in a friendly game */
const opponent = computed(() => {
  if (hostInvitation.value !== null) {
    return hostInvitation.value.kind === 'bot' ? (hostInvitation.value.bot?.name ?? 'your bot') : 'your friend'
  }

  return invitedBy.value
})
const eyebrow = computed(() => {
  if (hostInvitation.value !== null) {
    return `A friendly game · ${hostInvitation.value.kind === 'bot' ? 'Your bot' : 'A friend'}`
  }

  return invitedBy.value !== null ? 'A friendly game' : 'Play a game'
})
const waitingFor = computed(() => {
  if (hostInvitation.value !== null) {
    return hostInvitation.value.kind === 'bot' ? 'Waiting for your bot' : 'Waiting for your friend'
  }

  return invitedBy.value !== null ? `Waiting for ${invitedBy.value}` : 'Waiting for an opponent'
})

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
    if (!(await readInvitation(token))) {
      return
    }
    const decks = await fetchDecks(token)
    // a guest invited plays their clan of the way, if any
    choices.value = deckChoices(decks, session.value?.preferredClan ?? newcomer.clanId)
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
 * The invitation of the page: the one of the host, or who invites with the code. False when it cannot be used: the
 * host goes back to their invitations, a guest to the page of the code, which tells why.
 */
async function readInvitation(token: string): Promise<boolean> {
  try {
    if (invitationQuery !== null) {
      const invitation = await fetchSelfInvitation(token)
      if (invitation.id !== invitationQuery) {
        throw new ApiProblem(404, 'invitation_not_found', 'This invitation was replaced.')
      }
      friendly.value = { host: invitation }
    } else if (codeQuery !== null) {
      const status = await fetchInvitationStatus(codeQuery)
      if (status.state !== 'pending') {
        throw new ApiProblem(422, 'invalid_invitation', 'This invitation can no longer be used.')
      }
      friendly.value = { code: codeQuery, hostName: status.host_name }
    }
  } catch (problem) {
    if (!(problem instanceof ApiProblem)) {
      throw problem
    }
    // waiting with it already (the invitation of the host is used once matched): the queue tells
    if (codeQuery !== null) {
      await router.replace({ name: 'join', params: { code: codeQuery } })
    } else {
      await router.replace({ name: 'invite' })
    }

    return false
  }

  return true
}

function queueInvitation(): QueueInvitation | undefined {
  if (hostInvitation.value !== null) {
    return { invitation_id: hostInvitation.value.id }
  }

  return codeQuery !== null ? { invitation_code: codeQuery } : undefined
}

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
    await joinQueue(token, chosen.value, queueInvitation())
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

/**
 * Imports the deck of the URL, which becomes the first of the user's decks, chosen.
 */
async function importFromUrl(): Promise<void> {
  const token = session.value?.token
  const url = deckUrl.value.trim()
  if (!token || url === '' || importing.value) {
    return
  }
  importing.value = true
  importFailure.value = null
  notice.value = null
  try {
    const deck = await importDeck(token, url)
    const result = withImported(choices.value, deck)
    choices.value = result.choices
    chosen.value = deck.id
    const name = deckChoice(deck).name
    notice.value = result.alreadyOwned
      ? { before: 'You already have ', name, after: ', chosen.' }
      : { before: '', name, after: ' imported from EmeraldDB, and chosen.' }
    deckUrl.value = ''
  } catch (problem) {
    importFailure.value =
      problem instanceof ApiProblem ? importProblem(problem) : { summary: 'The deck cannot be imported.', details: [] }
  } finally {
    importing.value = false
  }
}

/**
 * Deletes a deck of the user, once confirmed: the deck chosen is no longer, the user chooses another.
 */
async function removeDeck(choice: DeckChoice): Promise<void> {
  const token = session.value?.token
  if (!token || deleting.value) {
    return
  }
  deleting.value = true
  error.value = null
  try {
    await deleteDeck(token, choice.deck.id)
    choices.value = withoutDeck(choices.value, choice.deck.id)
    if (chosen.value === choice.deck.id) {
      chosen.value = null
    }
    notice.value = { before: '', name: choice.name, after: ' deleted.' }
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'The deck cannot be deleted.'
  } finally {
    confirmingDeletion.value = null
    deleting.value = false
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
    // leaving cancelled the invitation
    if (hostInvitation.value !== null) {
      await router.push({ name: 'invite' })
    }
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

/**
 * A game against the house bot with the deck chosen, without the queue.
 */
async function faceHouseBot(): Promise<void> {
  const token = session.value?.token
  if (!token || chosen.value === null || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const game = await createBotGame(token, chosen.value)
    await open(game.game_id)
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : `${HOUSE_BOT.name} cannot be faced.`
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
      <div class="eyebrow">{{ eyebrow }}</div>
      <h1>Choose your deck</h1>
      <p v-if="opponent !== null" class="lead">
        You face <strong>{{ opponent }}</strong
        >, and nobody else. Not ranked.
      </p>
      <p v-else class="lead">
        Face the next player who joins the queue, or train against {{ HOUSE_BOT.name }}, {{ HOUSE_BOT.title }}.
      </p>
    </div>

    <section class="decks mine" aria-labelledby="mine">
      <div class="mine-heading">
        <div class="mine-title">
          <h2 id="mine" class="form-label">Your decks</h2>
          <p v-if="notice !== null" class="notice" role="status">
            {{ notice.before }}<strong>{{ notice.name }}</strong
            >{{ notice.after }}
          </p>
        </div>
        <form class="import" novalidate @submit.prevent="importFromUrl">
          <label for="deck-url">Import a deck from EmeraldDB</label>
          <input
            id="deck-url"
            v-model="deckUrl"
            class="field field-ui"
            type="url"
            placeholder="https://www.emeralddb.org/decks/…"
            :aria-invalid="importFailure !== null"
            :aria-describedby="importFailure !== null ? 'import-problem' : undefined"
          />
          <button type="submit" class="btn btn-ghost" :disabled="deckUrl.trim() === '' || importing">
            {{ importing ? 'Importing…' : 'Import' }}
          </button>
        </form>
      </div>
      <div v-if="importFailure !== null" id="import-problem" class="form-alert" role="alert">
        <p class="form-alert-title">{{ importFailure.summary }}</p>
        <p v-for="detail in importFailure.details" :key="detail" class="form-alert-text">{{ detail }}</p>
      </div>
      <div v-if="choices.mine.length > 0" class="grid">
        <div v-for="choice in choices.mine" :key="choice.deck.id" class="slot">
          <div
            v-if="confirmingDeletion === choice.deck.id"
            class="confirm"
            role="group"
            :aria-labelledby="`delete-${choice.deck.id}`"
          >
            <span :id="`delete-${choice.deck.id}`" class="confirm-title">Delete {{ choice.name }}?</span>
            <div class="confirm-actions">
              <button type="button" class="btn btn-seal" :disabled="deleting" @click="removeDeck(choice)">
                Delete
              </button>
              <button type="button" class="btn-link" @click="confirmingDeletion = null">Keep it</button>
            </div>
          </div>
          <template v-else>
            <button
              type="button"
              class="deck"
              :aria-pressed="chosen === choice.deck.id"
              @click="chosen = choice.deck.id"
            >
              <span class="kanji" :style="{ color: choice.clan?.color }">{{ choice.clan?.kanji ?? '' }}</span>
              <span class="name">{{ choice.name }}</span>
              <span class="detail">{{ [choice.clan?.name, stronghold(choice)].filter(Boolean).join(' · ') }}</span>
            </button>
            <button
              type="button"
              class="trash"
              :aria-label="`Delete ${choice.name}`"
              title="Delete this deck"
              @click="confirmingDeletion = choice.deck.id"
            >
              <ActionIcon name="discard" :size="20" />
            </button>
          </template>
        </div>
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
        {{ friendly === null ? 'Join the queue' : 'Enter the queue' }}
      </button>
      <span v-if="chosenDeck" class="with">
        with <strong>{{ chosenDeck.name }}</strong>
      </span>
      <template v-if="friendly === null">
        <span class="separator" aria-hidden="true"></span>
        <button
          type="button"
          class="btn btn-ghost btn-large bot"
          :disabled="!chosenDeck || sending"
          :aria-label="`Face ${HOUSE_BOT.name}, a bot${chosenDeck ? `, with ${chosenDeck.name}` : ''}`"
          @click="faceHouseBot"
        >
          <span class="bot-name">Face {{ HOUSE_BOT.name }}</span>
          <span class="bot-title">{{ HOUSE_BOT.role }} · a bot, to train</span>
        </button>
      </template>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <RouterLink v-if="hostInvitation !== null" :to="{ name: 'invite' }" class="btn-link aside">
        Back to the invitation
      </RouterLink>
      <RouterLink v-else-if="friendly === null" :to="{ name: 'invite' }" class="btn-link aside">
        Play a friendly game
      </RouterLink>
    </div>
  </div>

  <div v-else-if="state === 'waiting'" class="waiting screen">
    <ClanMon :src="enso" :size="168" class="enso" />
    <div class="status" role="status">
      <div class="eyebrow">
        <template v-if="friendly !== null">A friendly game · </template>In the queue ·
        {{ waitingTime((now - since) / 1000) }}
      </div>
      <h1>{{ waitingFor }}</h1>
      <p class="lead">
        <template v-if="waitingDeck">
          You play the <strong>{{ waitingDeck.name }}</strong
          >{{ waitingDeck.deck.owner_id === null ? ' starter deck' : '' }}.
        </template>
        <template v-if="hostInvitation?.kind === 'bot'">
          The game opens as soon as your bot enters the queue with its key.
        </template>
        <template v-else-if="hostInvitation !== null">
          The game opens as soon as your friend enters the queue with the link.
        </template>
        <template v-else-if="invitedBy !== null">The game opens as soon as {{ invitedBy }} enters the queue.</template>
        <template v-else>The game opens as soon as another player joins.</template>
      </p>
    </div>
    <p v-if="hostInvitation !== null" class="expiry">
      The invitation expires in {{ timeLeft(hostInvitation.expires_at, now) }} if nobody comes.
    </p>
    <button type="button" class="btn btn-ghost" :disabled="sending" @click="leave">
      {{ hostInvitation !== null ? 'Leave the queue and cancel the invitation' : 'Leave the queue' }}
    </button>
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

.mine-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.mine-title {
  display: flex;
  align-items: baseline;
  gap: 18px;
}

.notice {
  font-size: 14px;
  color: var(--ink-2);
}

.notice strong {
  font-family: var(--font-display);
  font-size: 16px;
}

.import {
  display: flex;
  align-items: center;
  gap: 10px;
}

.import label {
  font-size: 14px;
  color: var(--ink-2);
}

.import .field {
  width: 440px;
  min-height: 44px;
  padding: 0 14px;
  font-size: 15px;
  background: var(--card);
  border-color: var(--line);
}

.import .field:focus {
  outline: none;
  border-color: var(--ink);
}

.import .field[aria-invalid='true'] {
  border-color: var(--seal);
}

.import .btn {
  min-height: 44px;
  padding: 0 20px;
  font-size: 15px;
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

@media (hover: hover) {
  .deck:hover {
    border-color: var(--ink);
  }
}

.slot {
  position: relative;
  display: flex;
}

.slot .deck {
  flex: 1;
  min-width: 0;
}

.slot .deck .name {
  padding-right: 28px;
}

/* always shown: a touch screen has no hover */
.trash {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 0;
  border-radius: 2px;
  color: var(--ink-3);
  cursor: pointer;
}

.trash:focus-visible {
  color: var(--seal);
  background: var(--paper-2);
}

@media (hover: hover) {
  .trash:hover {
    color: var(--seal);
    background: var(--paper-2);
  }
}

.confirm {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 14px 12px;
  min-height: 132px;
  box-sizing: border-box;
  background: var(--card);
  border: 1.5px solid var(--ink);
  border-radius: 2px;
}

.confirm-title {
  font-size: 15px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.confirm-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-seal {
  background: var(--seal);
  border-color: var(--seal);
  color: var(--seal-ink);
}

@media (hover: hover) {
  .btn-seal:hover {
    background: var(--seal);
    color: var(--seal-ink);
    filter: brightness(0.92);
  }
}

.confirm-actions .btn {
  min-height: 44px;
  padding: 0 14px;
  font-size: 14px;
}

.deck[aria-pressed='true'] {
  border: 2px solid var(--seal);
  background: var(--selected);
}

.kanji {
  font-family: 'Shippori Mincho', serif;
  font-weight: 700;
  /* the three kanji of the Unicorn on one line, down to an iPad mini */
  font-size: clamp(24px, 2.3vw, 30px);
  white-space: nowrap;
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

.separator {
  width: 1px;
  height: 40px;
  background: var(--line);
}

.bot {
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 1px;
}

.bot-name {
  font-family: var(--font-display);
  font-size: 18px;
  line-height: 1.1;
}

.bot-title {
  font-size: 12px;
  font-weight: 500;
  color: var(--ink-3);
}

.aside {
  margin-left: auto;
}

.expiry {
  font-size: 14px;
  color: var(--ink-3);
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

@media (hover: hover) {
  .waiting .btn-ghost:hover {
    color: var(--seal);
    border-color: var(--seal);
    background: transparent;
  }
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

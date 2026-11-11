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
  fetchSelfGames,
  fetchSelfInvitation,
  importDeck,
  joinQueue,
  type Card,
  type Deck,
  type Invitation,
  type QueueInvitation,
} from '@/api/client'
import ActionIcon from '@/components/ActionIcon.vue'
import ClanMon from '@/components/ClanMon.vue'
import QueueWaiting from '@/components/queue/QueueWaiting.vue'
import { HOUSE_BOT } from '@/data/houseBot'
import { newcomer } from '@/lib/newcomer'
import { inverted } from '@/lib/page'
import {
  deckChoice,
  deckChoices,
  importProblem,
  withImported,
  withoutDeck,
  type DeckChoice,
  type DeckChoices,
  type ImportProblem,
} from '@/lib/queue'
import { checkQueue, startWaiting, waiting, type Friendly } from '@/lib/queueing'
import { isGuest, session } from '@/lib/session'

/**
 * The matchmaking queue: the user chooses a deck and joins the queue. An account then waits where it likes (the home
 * tells the wait, and a game may be watched meanwhile); a guest, who cannot watch, waits here (the page in reverse).
 * The game opens wherever they are once the queue has matched them (`lib/queueing.ts`, `QueueFollower`). A user who
 * plays a game that has not ended finishes it first.
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
const state = ref<'choosing' | null>(null)
const choices = ref<DeckChoices>({ mine: [], starters: [], preselected: null })
const chosen = ref<string | null>(null)
/** a game of the user has not ended: they finish it first */
const gameInProgress = ref(false)
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
/** the strongholds of the decks, by code, read from the API */
const strongholds = reactive<Record<string, Card>>({})

const allChoices = computed(() => [...choices.value.mine, ...choices.value.starters])
const chosenDeck = computed(() => allChoices.value.find((choice) => choice.deck.id === chosen.value))
/** a guest waits on this page; an account goes home */
const account = computed(() => session.value !== null && !isGuest(session.value))
const waitingHere = computed(() => waiting.value !== null && !account.value)

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

watch(waitingHere, (current) => (inverted.value = current), { immediate: true })
// once in the queue, an account goes home
watch(
  () => waiting.value !== null && account.value,
  (gone) => {
    if (gone) {
      void router.push({ name: 'landing' })
    }
  },
  { immediate: true },
)

onMounted(async () => {
  const token = session.value?.token
  if (!token) {
    return
  }
  try {
    if (!(await readInvitation(token))) {
      return
    }
    const decks = await fetchDecks(token)
    // a guest invited plays their clan of the way, if any
    choices.value = deckChoices(decks, session.value?.preferredClan ?? newcomer.clanId)
    loadStrongholds(decks)
    chosen.value = choices.value.preselected
    gameInProgress.value = (await fetchSelfGames(token)).some((game) => !game.ended)
    await checkQueue(token, session.value!.userId)
  } catch (problem) {
    if (!(problem instanceof ApiProblem)) {
      throw problem
    }
    error.value = problem.detail
  }
  state.value = 'choosing'
})

onBeforeUnmount(() => {
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

/** whom the user waits for in a friendly game */
function friendlyWait(): Friendly | null {
  if (hostInvitation.value !== null) {
    const { kind, bot, expires_at: expiresAt } = hostInvitation.value

    return kind === 'bot' ? { kind, botName: bot?.name ?? null, expiresAt } : { kind, expiresAt }
  }

  return invitedBy.value !== null ? { kind: 'host', hostName: invitedBy.value } : null
}

function queueInvitation(): QueueInvitation | undefined {
  if (hostInvitation.value !== null) {
    return { invitation_id: hostInvitation.value.id }
  }

  return codeQuery !== null ? { invitation_code: codeQuery } : undefined
}

async function join(): Promise<void> {
  const current = session.value
  const deck = chosenDeck.value
  if (current === null || deck === undefined || sending.value || gameInProgress.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await joinQueue(current.token, deck.deck.id, queueInvitation())
  } catch (problem) {
    // already in the queue (another tab): wait with that deck
    if (!(problem instanceof ApiProblem) || problem.type !== 'already_queued') {
      error.value = problem instanceof ApiProblem ? problem.detail : 'The queue cannot be joined.'
      sending.value = false

      return
    }
  }
  startWaiting(
    current.userId,
    { id: deck.deck.id, name: deck.name, starter: deck.deck.owner_id === null },
    friendlyWait(),
  )
  try {
    // matched at once when another player waited, maybe before the hub could tell
    await checkQueue(current.token, current.userId)
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
    loadStrongholds([deck])
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

/**
 * A game against the house bot with the deck chosen, without the queue.
 */
async function faceHouseBot(): Promise<void> {
  const token = session.value?.token
  if (!token || chosen.value === null || sending.value || gameInProgress.value) {
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
  await router.push({ name: 'game', params: { id: gameId } })
}

/** reads the strongholds of the decks not read yet */
function loadStrongholds(decks: Deck[]): void {
  for (const code of new Set(decks.map((deck) => deck.stronghold))) {
    if (code === null || code in strongholds) {
      continue
    }
    fetchCard(code)
      .then((card) => (strongholds[code] = card))
      .catch(() => {
        // the deck is shown without its stronghold
      })
  }
}

function stronghold(choice: DeckChoice): string | undefined {
  return choice.deck.stronghold === null ? undefined : strongholds[choice.deck.stronghold]?.name
}
</script>

<template>
  <QueueWaiting v-if="waitingHere && waiting !== null" :waiting="waiting" />
  <div v-else-if="state === 'choosing' && waiting === null" class="queue screen">
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
              <span class="mon-slot">
                <ClanMon v-if="choice.clan" :src="choice.clan.mon" :color="choice.clan.color" :size="36" />
              </span>
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
          <span class="mon-slot">
            <ClanMon v-if="choice.clan" :src="choice.clan.mon" :color="choice.clan.color" :size="36" />
          </span>
          <span class="name">{{ choice.name }}</span>
          <span class="detail">{{ stronghold(choice) }}</span>
        </button>
      </div>
    </section>

    <div class="actions">
      <button
        type="button"
        class="btn btn-primary btn-large"
        :disabled="!chosenDeck || sending || gameInProgress"
        @click="join"
      >
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
          :disabled="!chosenDeck || sending || gameInProgress"
          :aria-label="`Face ${HOUSE_BOT.name}, a bot${chosenDeck ? `, with ${chosenDeck.name}` : ''}`"
          @click="faceHouseBot"
        >
          <span class="bot-name">Face {{ HOUSE_BOT.name }}</span>
          <span class="bot-title">{{ HOUSE_BOT.role }} · a bot, to train</span>
        </button>
      </template>
      <p v-if="gameInProgress" class="form-error in-progress" role="alert">
        Your game is still in progress: finish it before starting another one.
      </p>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <RouterLink v-if="hostInvitation !== null" :to="{ name: 'invite' }" class="btn-link aside">
        Back to the invitation
      </RouterLink>
      <RouterLink v-else-if="friendly === null" :to="{ name: 'invite' }" class="btn-link aside">
        Play a friendly game
      </RouterLink>
    </div>
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

.mon-slot {
  display: flex;
  min-height: 36px;
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

.in-progress {
  flex-basis: 100%;
}
</style>

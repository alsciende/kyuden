<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import {
  ApiProblem,
  cancelTournament,
  fetchDecks,
  fetchTournament,
  registerInTournament,
  tournamentCalendarUrl,
  unregisterFromTournament,
  type Deck,
  type Tournament,
} from '@/api/client'
import PlayerNobori from '@/components/PlayerNobori.vue'
import { deckChoices, type DeckChoice } from '@/lib/queue'
import { isGuest, session } from '@/lib/session'
import { entryIn, refreshTournamentEntries } from '@/lib/tournamentEntries'
import { bracketSize, isOpen, startLabel, viewerTimeZone, weekdayTimeLabel } from '@/lib/tournaments'

/**
 * A tournament, public: its start in the time zone of the viewer (named), its organizer, its schedule and its players
 * (their nobori, never their decks). An account registers with one of its decks, changes it or unregisters until the
 * tournament locks, one hour before it begins; its organizer cancels it until then. A visitor or a guest creates an
 * account or signs in to register, and comes back here.
 */
const route = useRoute()
const id = computed(() => String(route.params.id))
const timeZone = viewerTimeZone()

const tournament = ref<Tournament | null>(null)
const notFound = ref(false)
const loadError = ref<string | null>(null)
const now = ref(new Date())

async function load(): Promise<void> {
  try {
    tournament.value = await fetchTournament(id.value)
    now.value = new Date()
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.status === 404) {
      notFound.value = true
    } else {
      loadError.value = 'The tournament could not be read. Try again in a moment.'
    }
  }
}

const account = computed(() => session.value !== null && session.value.username !== null)
const token = computed(() => (account.value ? (session.value?.token ?? null) : null))
const entry = computed(() => entryIn(id.value))
const organizer = computed(() => tournament.value?.organizer.user_id === session.value?.userId && account.value)
const open = computed(() => tournament.value !== null && isOpen(tournament.value, now.value))
const cancelled = computed(() => tournament.value?.status === 'cancelled')
const full = computed(
  () => tournament.value !== null && tournament.value.players.length >= tournament.value.max_entrants,
)
const bracket = computed(() => bracketSize(tournament.value?.players.length ?? 0))
const back = computed(() => ({ next: `/tournaments/${id.value}` }))

/** the steps of the schedule, the one the tournament stands at told "Now" */
const schedule = computed(() => {
  const current = tournament.value
  if (current === null) {
    return []
  }
  const time = now.value.getTime()
  const locked = time >= Date.parse(current.locks_at)
  const started = time >= Date.parse(current.starts_at)

  return [
    { key: 'open', when: 'Now', what: 'Registrations open', now: !locked },
    {
      key: 'locked',
      when: locked && !started ? 'Now' : weekdayTimeLabel(current.locks_at, timeZone),
      what: 'Locked: decks, registrations, bracket',
      now: locked && !started,
    },
    {
      key: 'round',
      when: started ? 'Now' : weekdayTimeLabel(current.starts_at, timeZone),
      what: 'Round 1: pairings revealed',
      now: started,
    },
  ].filter((step) => step.key !== 'open' || step.now)
})

const decks = ref<Deck[]>([])
const choices = computed(() => deckChoices(decks.value, session.value?.preferredClan ?? null))
const deckId = ref<string | null>(null)
const starterName = (choice: DeckChoice) => (choice.clan ? `${choice.clan.name} starter deck` : choice.name)

watch(
  [entry, choices],
  () => {
    deckId.value = entry.value?.deck_id ?? deckId.value ?? choices.value.preselected
  },
  { immediate: true },
)

onMounted(async () => {
  await load()
  if (token.value !== null) {
    await refreshTournamentEntries(token.value)
    try {
      decks.value = await fetchDecks(token.value)
    } catch {
      // the select stays empty: registering tells why
    }
  }
})

const busy = ref(false)
const error = ref<string | null>(null)
const confirmingCancel = ref(false)

async function act(action: (token: string) => Promise<unknown>): Promise<void> {
  if (token.value === null || busy.value) {
    return
  }
  busy.value = true
  error.value = null
  try {
    await action(token.value)
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'Something went wrong. Try again in a moment.'
  } finally {
    busy.value = false
    confirmingCancel.value = false
    await Promise.all([load(), refreshTournamentEntries(token.value)])
  }
}

const register = () => act((token) => registerInTournament(token, id.value, deckId.value ?? ''))
const changeDeck = () => (entry.value && deckId.value !== entry.value.deck_id ? register() : undefined)
const unregister = () => act((token) => unregisterFromTournament(token, id.value))
const cancel = () => act((token) => cancelTournament(token, id.value))
</script>

<template>
  <div class="tournament screen">
    <div v-if="notFound" class="heading">
      <RouterLink :to="{ name: 'tournaments' }" class="back">Tournaments</RouterLink>
      <h1>No such tournament</h1>
      <p class="sub">It may never have existed: see the tournaments planned.</p>
    </div>
    <p v-else-if="loadError" class="form-error" role="alert">{{ loadError }}</p>
    <template v-else-if="tournament">
      <div class="heading">
        <RouterLink :to="{ name: 'tournaments' }" class="back">Tournaments</RouterLink>
        <h1 :class="{ struck: cancelled }">{{ startLabel(tournament.starts_at, timeZone) }}</h1>
        <p class="sub">Single elimination · organized by {{ tournament.organizer.name }} · {{ timeZone }} time</p>
      </div>

      <div v-if="cancelled" class="form-alert" role="status">
        <span class="form-alert-title">This tournament is cancelled</span>
        <span class="form-alert-text">Its organizer cancelled it: it will not be played.</span>
      </div>
      <ol v-else aria-label="Schedule" class="schedule">
        <li v-for="step in schedule" :key="step.key" :class="{ now: step.now }">
          <span class="when">{{ step.when }}</span>
          <span class="what">{{ step.what }}</span>
        </li>
      </ol>

      <div class="columns">
        <section class="players" aria-labelledby="players-title">
          <div class="players-heading">
            <h2 id="players-title">
              {{ tournament.players.length }} player{{ tournament.players.length === 1 ? '' : 's' }}
              {{ cancelled ? 'still registered' : 'registered' }}
            </h2>
            <span v-if="open && bracket !== null" class="hint">bracket of {{ bracket }} if it began now</span>
          </div>
          <ul v-if="tournament.players.length > 0">
            <li v-for="player in tournament.players" :key="player.user_id">
              <span class="nobori"><PlayerNobori :banner="player.banner" :width="14" decorative /></span>
              {{ player.name }}
              <span v-if="player.user_id === session?.userId" class="hint">(you)</span>
            </li>
          </ul>
          <p v-else class="hint">Nobody has registered yet.</p>
          <p class="hint">Each player shows their nobori here; the decks stay secret.</p>
        </section>

        <aside v-if="!account && open" class="panel">
          <div class="form-label">Play in it</div>
          <h2>Register with an account</h2>
          <p class="lead">
            <template v-if="isGuest(session)">
              Create your account, choose your deck, and come back here to register: you keep your games and trophies in
              it.
            </template>
            <template v-else>
              Create an account, choose your deck, and come back here to register. A guest keeps their games and
              trophies in the account they create.
            </template>
          </p>
          <div class="panel-actions">
            <RouterLink :to="{ name: 'signup', query: back }" class="btn btn-primary btn-large"
              >Create an account</RouterLink
            >
            <RouterLink :to="{ name: 'login', query: back }" class="btn-link">Sign in</RouterLink>
          </div>
        </aside>

        <aside v-else-if="account && (entry || open || organizer)" class="panel" :class="{ registered: entry }">
          <template v-if="entry">
            <div class="form-label seal">You are registered</div>
            <template v-if="open">
              <div class="form-field">
                <label for="deck">Your deck</label>
                <select id="deck" v-model="deckId" class="field field-ui" :disabled="busy" @change="changeDeck">
                  <optgroup v-if="choices.mine.length > 0" label="Your decks">
                    <option v-for="choice in choices.mine" :key="choice.deck.id" :value="choice.deck.id">
                      {{ choice.name }}
                    </option>
                  </optgroup>
                  <optgroup label="Starter decks">
                    <option v-for="choice in choices.starters" :key="choice.deck.id" :value="choice.deck.id">
                      {{ starterName(choice) }}
                    </option>
                  </optgroup>
                </select>
              </div>
              <p class="hint">You may change it until {{ weekdayTimeLabel(tournament.locks_at, timeZone) }}.</p>
            </template>
            <p v-else-if="!cancelled" class="lead">
              With {{ entry.deck_name ?? 'your deck' }}. Everything is locked now: the bracket is drawn.
            </p>
            <div class="panel-actions">
              <a v-if="!cancelled" :href="tournamentCalendarUrl(tournament.id)" download class="btn-link calendar"
                >Add to your calendar</a
              >
              <button v-if="open || cancelled" type="button" class="btn-link" :disabled="busy" @click="unregister">
                Unregister
              </button>
            </div>
          </template>

          <template v-else-if="open">
            <div class="form-label">Play in it</div>
            <template v-if="full">
              <h2>The tournament is full</h2>
              <p class="lead">It takes {{ tournament.max_entrants }} players at most.</p>
            </template>
            <template v-else>
              <h2>Register with a deck</h2>
              <div class="form-field">
                <label for="deck">Your deck</label>
                <select id="deck" v-model="deckId" class="field field-ui" :disabled="busy">
                  <optgroup v-if="choices.mine.length > 0" label="Your decks">
                    <option v-for="choice in choices.mine" :key="choice.deck.id" :value="choice.deck.id">
                      {{ choice.name }}
                    </option>
                  </optgroup>
                  <optgroup label="Starter decks">
                    <option v-for="choice in choices.starters" :key="choice.deck.id" :value="choice.deck.id">
                      {{ starterName(choice) }}
                    </option>
                  </optgroup>
                </select>
              </div>
              <p class="hint">
                You play the whole tournament with it, and may change it until
                {{ weekdayTimeLabel(tournament.locks_at, timeZone) }}.
              </p>
              <button
                type="button"
                class="btn btn-primary btn-large"
                :disabled="busy || deckId === null"
                @click="register"
              >
                Register
              </button>
            </template>
          </template>

          <div v-if="organizer && open" class="organizer">
            <template v-if="confirmingCancel">
              <span class="confirm">Cancel the tournament? Its players are told, and it cannot be undone.</span>
              <span class="confirm-actions">
                <button type="button" class="btn btn-primary" :disabled="busy" @click="cancel">Cancel it</button>
                <button type="button" class="btn-link" @click="confirmingCancel = false">Keep it</button>
              </span>
            </template>
            <template v-else>
              <button type="button" class="btn-link seal" @click="confirmingCancel = true">
                Cancel the tournament
              </button>
              <span class="hint">
                You organize it: you may cancel it until {{ weekdayTimeLabel(tournament.locks_at, timeZone) }}.
              </span>
            </template>
          </div>

          <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        </aside>

        <aside v-else-if="!cancelled && !open" class="panel">
          <div class="form-label">Registrations closed</div>
          <p class="lead">Everything is locked one hour before the tournament begins.</p>
        </aside>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tournament {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 44px 96px 40px;
}

.heading {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.back {
  align-self: flex-start;
  font-size: 15px;
  font-weight: 500;
}

h1 {
  font-size: 56px;
  line-height: 1;
}

h1.struck {
  text-decoration: line-through;
  text-decoration-thickness: 2px;
  color: var(--ink-2);
}

.sub {
  font-size: 17px;
  color: var(--ink-2);
}

.schedule {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-auto-columns: minmax(0, 1fr);
  grid-auto-flow: column;
  border-top: 3px solid var(--ink);
}

.schedule li {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-top: 12px;
}

.schedule li + li {
  padding-left: 16px;
  border-left: 1.5px solid var(--line);
}

.schedule .when {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.schedule .now .when {
  color: var(--seal);
}

.schedule .what {
  font-size: 16px;
  font-weight: 600;
}

.columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 460px;
  gap: 28px;
  align-items: start;
}

.players,
.panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 28px 32px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.panel {
  gap: 18px;
  padding: 28px;
  border-color: var(--ink);
}

.players-heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
}

h2 {
  font-size: 30px;
  line-height: 1.1;
}

.players ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 24px;
}

.players li {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  font-size: 16px;
  border-top: 1px solid var(--paper-3);
  overflow-wrap: anywhere;
}

.players li:last-child,
.players li:nth-last-child(2):nth-child(odd) {
  border-bottom: 1px solid var(--paper-3);
}

.nobori {
  display: flex;
  flex: none;
  height: 28px;
}

.hint {
  font-size: 14px;
  color: var(--ink-2);
}

.lead {
  font-size: 16px;
  line-height: 1.5;
  color: var(--ink-2);
}

.panel .form-label {
  color: var(--ink-3);
}

.panel .form-label.seal {
  color: var(--seal);
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: 24px;
}

.panel .btn-link {
  align-self: flex-start;
  padding-left: 0;
  font-weight: 600;
}

.btn-link.seal {
  color: var(--seal);
}

.organizer {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--paper-3);
}

.confirm {
  font-size: 15px;
}

.confirm-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}
</style>

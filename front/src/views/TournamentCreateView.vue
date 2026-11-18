<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { ApiProblem, createTournament, fetchTournaments, type TournamentSummary } from '@/api/client'
import { session } from '@/lib/session'
import {
  conflictOf,
  earliestStart,
  LOCK_LEAD_MS,
  localDateOf,
  slotsOfDay,
  startLabel,
  timeLabel,
  viewerTimeZone,
  weekdayTimeLabel,
} from '@/lib/tournaments'

/**
 * An account creates a tournament with its start only: a day and a time of the time zone of the browser, every half
 * hour, at least 12 hours ahead. The form tells at once what the API would refuse (another tournament within 12
 * hours, one the user organizes already), from the list of the tournaments; the API checks it again.
 */
const router = useRouter()
const timeZone = viewerTimeZone()
const now = new Date()
const PREFERRED_TIME = '20:00'

const tournaments = ref<TournamentSummary[]>([])
onMounted(async () => {
  try {
    tournaments.value = await fetchTournaments()
  } catch {
    // the API checks the dates anyway
  }
})

const minDay = localDateOf(earliestStart(now), timeZone)
const day = ref(minDay)
const slots = computed(() => slotsOfDay(day.value, timeZone, now))
const startsAt = ref<string | null>(null)

/** the same time on another day when it has it, else the preferred time, else the first one */
watch(
  slots,
  (current, previous) => {
    const kept = previous?.find((slot) => slot.iso === startsAt.value)?.label ?? PREFERRED_TIME
    startsAt.value = (current.find((slot) => slot.label === kept) ?? current[0])?.iso ?? null
  },
  { immediate: true },
)

/** "Your time zone, Europe/Paris: 20:00" */
const zoneHint = computed(
  () => `Your time zone, ${timeZone}${startsAt.value === null ? '' : `: ${timeLabel(startsAt.value, timeZone)}`}`,
)

/** "UTC: 19:00", the time the tournament is stored at */
const utcHint = computed(() => (startsAt.value === null ? null : `UTC: ${startsAt.value.slice(11, 16)}`))

const lockTime = computed(() =>
  startsAt.value === null
    ? null
    : timeLabel(new Date(Date.parse(startsAt.value) - LOCK_LEAD_MS).toISOString(), timeZone),
)

const conflict = computed(() => (startsAt.value === null ? null : conflictOf(startsAt.value, tournaments.value)))
const organized = computed(() =>
  tournaments.value.find(
    (tournament) => tournament.organizer.user_id === session.value?.userId && tournament.status !== 'cancelled',
  ),
)

const sending = ref(false)
const error = ref<string | null>(null)
const canCreate = computed(
  () => startsAt.value !== null && conflict.value === null && organized.value === undefined && !sending.value,
)

async function create(): Promise<void> {
  const token = session.value?.token
  if (!token || startsAt.value === null || !canCreate.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const tournament = await createTournament(token, startsAt.value)
    await router.push({ name: 'tournament', params: { id: tournament.id } })
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'The tournament could not be created.'
    // another tournament may have been created meanwhile
    tournaments.value = await fetchTournaments().catch(() => tournaments.value)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="create screen">
    <div class="heading">
      <RouterLink :to="{ name: 'tournaments' }" class="back">Tournaments</RouterLink>
      <h1>Create a tournament</h1>
    </div>
    <div class="columns">
      <form class="form" @submit.prevent="create">
        <div class="form-label">When it begins</div>
        <div class="fields">
          <div class="form-field">
            <label for="day">Day</label>
            <input id="day" v-model="day" type="date" class="field field-ui" :min="minDay" required />
          </div>
          <div class="form-field">
            <label for="time">Time</label>
            <select id="time" v-model="startsAt" class="field field-ui" :disabled="slots.length === 0">
              <option v-for="slot in slots" :key="slot.iso" :value="slot.iso">{{ slot.label }}</option>
            </select>
          </div>
        </div>
        <p class="form-hint">
          {{ zoneHint }}<template v-if="utcHint"><br />{{ utcHint }}</template>
        </p>
        <div v-if="slots.length === 0" class="form-alert" role="alert">
          <span class="form-alert-title">No time left on this day</span>
          <span class="form-alert-text">A tournament begins at least 12 hours from now: choose a later day.</span>
        </div>
        <div v-if="organized" class="form-alert" role="alert">
          <span class="form-alert-title">You organize a tournament already</span>
          <span class="form-alert-text">
            One at a time:
            <RouterLink :to="{ name: 'tournament', params: { id: organized.id } }">{{
              startLabel(organized.starts_at, timeZone)
            }}</RouterLink
            >. Cancel it to plan another one.
          </span>
        </div>
        <div v-else-if="conflict" class="form-alert" role="alert">
          <span class="form-alert-title"
            >Another tournament begins {{ startLabel(conflict.other.starts_at, timeZone) }}</span
          >
          <span class="form-alert-text">
            Two tournaments stand at least 12 hours apart: choose
            {{ weekdayTimeLabel(conflict.earlierLimit, timeZone) }} or earlier, or
            {{ weekdayTimeLabel(conflict.laterLimit, timeZone) }} or later.
          </span>
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <div class="actions">
          <button type="submit" class="btn btn-primary btn-large" :disabled="!canCreate">Create the tournament</button>
          <RouterLink :to="{ name: 'tournaments' }" class="btn-link">Cancel</RouterLink>
        </div>
      </form>
      <aside class="how">
        <div class="form-label">How it goes</div>
        <ol>
          <li>It begins at least 12 hours from now, and you organize one upcoming tournament at a time.</li>
          <li>Players register with a deck, yourself included: creating it does not register you.</li>
          <li>
            One hour before it begins, everything is locked: decks, registrations, cancelling. The bracket is the
            smallest of 2, 4, 8… 64 places holding the players; the empty places are byes.
          </li>
          <li>
            Single elimination. Each round, the players still in play join the queue and face their opponent of the
            round.
          </li>
        </ol>
        <p v-if="lockTime !== null" class="form-hint">This one would lock at {{ lockTime }}.</p>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.create {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 32px;
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

.columns {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 28px;
  align-items: start;
}

.form,
.how {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 32px;
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.form {
  background: var(--card);
}

.form > .form-label,
.how > .form-label {
  color: var(--ink-3);
}

.fields {
  display: flex;
  gap: 20px;
}

.fields .field {
  min-width: 180px;
}

.actions {
  display: flex;
  align-items: center;
  gap: 24px;
}

.how ol {
  margin: 0;
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  font-size: 16px;
  line-height: 1.45;
  color: var(--ink-2);
}
</style>

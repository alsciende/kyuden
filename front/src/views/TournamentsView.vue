<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { fetchTournaments, type TournamentSummary } from '@/api/client'
import { session } from '@/lib/session'
import { tournamentEntries } from '@/lib/tournamentEntries'
import { dayLabel, groupTournaments, inDaysLabel, timeLabel, viewerTimeZone } from '@/lib/tournaments'

/**
 * The tournaments at court, public: in progress, upcoming (those the user is registered in marked), and cancelled,
 * the first to begin first, their dates in the time zone of the viewer. An account creates one.
 */
const tournaments = ref<TournamentSummary[] | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    tournaments.value = await fetchTournaments()
  } catch {
    error.value = 'The tournaments could not be read. Try again in a moment.'
  }
})

const groups = computed(() => (tournaments.value === null ? null : groupTournaments(tournaments.value)))
const registered = computed(() => new Set(tournamentEntries.value.map((entry) => entry.tournament.id)))
const timeZone = viewerTimeZone()
const now = new Date()
const account = computed(() => session.value !== null && session.value.username !== null)

const players = (count: number) => `${count} player${count === 1 ? '' : 's'}`
</script>

<template>
  <div class="tournaments screen">
    <div class="heading">
      <div class="heading-text">
        <div class="eyebrow">Single elimination</div>
        <h1>Tournaments</h1>
      </div>
      <RouterLink :to="{ name: 'tournament-create' }" class="btn btn-primary btn-large">Create a tournament</RouterLink>
    </div>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <template v-else-if="groups !== null">
      <div v-if="tournaments?.length === 0" class="empty">
        <p>No tournament is planned.</p>
        <p class="hint">
          {{ account ? 'Create the first one: the players register with a deck.' : 'An account creates one.' }}
        </p>
      </div>

      <section v-if="groups.inProgress.length > 0" class="group" aria-labelledby="in-progress">
        <h2 id="in-progress" class="form-label">In progress</h2>
        <RouterLink
          v-for="tournament in groups.inProgress"
          :key="tournament.id"
          :to="{ name: 'tournament', params: { id: tournament.id } }"
          class="row in-progress"
        >
          <span class="when">
            <span class="day">{{ dayLabel(tournament.starts_at, timeZone) }}</span>
            <span class="time">began at {{ timeLabel(tournament.starts_at, timeZone) }}</span>
          </span>
          <span class="what">{{ players(tournament.entrants) }}</span>
          <span class="go">Follow</span>
        </RouterLink>
      </section>

      <section v-if="groups.upcoming.length > 0" class="group" aria-labelledby="upcoming">
        <h2 id="upcoming" class="form-label">Upcoming</h2>
        <RouterLink
          v-for="tournament in groups.upcoming"
          :key="tournament.id"
          :to="{ name: 'tournament', params: { id: tournament.id } }"
          class="row"
        >
          <span class="when">
            <span class="day">{{ dayLabel(tournament.starts_at, timeZone) }}</span>
            <span class="time"
              >at {{ timeLabel(tournament.starts_at, timeZone) }},
              {{ inDaysLabel(tournament.starts_at, now, timeZone) }}</span
            >
          </span>
          <span class="what">
            {{ players(tournament.entrants) }} registered
            <span v-if="registered.has(tournament.id)" class="tag">You are registered</span>
          </span>
          <span class="go">See it</span>
        </RouterLink>
      </section>

      <section v-if="groups.cancelled.length > 0" class="group" aria-labelledby="cancelled">
        <h2 id="cancelled" class="form-label">Cancelled</h2>
        <RouterLink
          v-for="tournament in groups.cancelled"
          :key="tournament.id"
          :to="{ name: 'tournament', params: { id: tournament.id } }"
          class="row cancelled"
        >
          <span class="day">{{ dayLabel(tournament.starts_at, timeZone) }}</span>
          <span class="what"
            >Cancelled by its organizer<template v-if="tournament.entrants > 0">
              · {{ players(tournament.entrants) }} still registered</template
            ></span
          >
          <span class="go">See it</span>
        </RouterLink>
      </section>

      <p class="zone">Times are those of your time zone, {{ timeZone }}.</p>
    </template>
  </div>
</template>

<style scoped>
.tournaments {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 32px;
  padding: 44px 96px 40px;
}

.heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.heading-text {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

h1 {
  font-size: 56px;
  line-height: 1;
}

.group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.group .form-label {
  color: var(--ink-3);
  font-family: var(--font-ui);
}

.row {
  display: grid;
  grid-template-columns: 320px 1fr auto;
  align-items: center;
  gap: 24px;
  min-height: 76px;
  padding: 0 24px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
  color: var(--ink);
  text-decoration: none;
}

@media (hover: hover) {
  .row:hover {
    border-color: var(--ink);
  }
}

.row.in-progress {
  border-color: var(--seal);
}

.row.cancelled {
  min-height: 64px;
  background: transparent;
  border-style: dashed;
  color: var(--ink-2);
}

.when {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.day {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 24px;
}

.cancelled .day {
  font-weight: 500;
  font-size: 22px;
  text-decoration: line-through;
  text-decoration-thickness: 1px;
}

.time {
  font-size: 14px;
  color: var(--ink-2);
}

.what {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 16px;
  color: var(--ink-2);
}

.tag {
  padding: 3px 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  background: var(--paper-2);
  border-radius: 2px;
}

.go {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}

.empty {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 18px;
}

.hint,
.zone {
  font-size: 14px;
  color: var(--ink-2);
}

.zone {
  margin-top: auto;
}
</style>

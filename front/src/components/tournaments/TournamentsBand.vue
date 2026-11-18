<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { fetchTournaments, type TournamentSummary } from '@/api/client'
import { groupTournaments } from '@/lib/tournaments'

/**
 * On the home (and the way of the newcomers), the tournaments at court: how many are in progress and upcoming, and
 * cancelled, muted, when there is one; shown only when a tournament is in progress or upcoming.
 */
const tournaments = ref<TournamentSummary[] | null>(null)

onMounted(async () => {
  try {
    tournaments.value = await fetchTournaments()
  } catch {
    // no band: the list tells when the tournaments cannot be read
  }
})

const groups = computed(() => (tournaments.value === null ? null : groupTournaments(tournaments.value)))
const shown = computed(() => groups.value !== null && groups.value.inProgress.length + groups.value.upcoming.length > 0)
</script>

<template>
  <section v-if="shown && groups" class="band" aria-labelledby="tournaments-title">
    <div class="text">
      <div class="form-label">Tournaments</div>
      <h2 id="tournaments-title">Tournaments at court</h2>
      <p class="lead">Single elimination, one deck for the whole tournament.</p>
    </div>
    <dl class="counts">
      <div v-if="groups.inProgress.length > 0" class="count in-progress">
        <dt>in progress</dt>
        <dd>{{ groups.inProgress.length }}</dd>
      </div>
      <div v-if="groups.upcoming.length > 0" class="count">
        <dt>upcoming</dt>
        <dd>{{ groups.upcoming.length }}</dd>
      </div>
      <div v-if="groups.cancelled.length > 0" class="count cancelled">
        <dt>cancelled</dt>
        <dd>{{ groups.cancelled.length }}</dd>
      </div>
    </dl>
    <RouterLink :to="{ name: 'tournaments' }" class="btn btn-ghost btn-large">See the tournaments</RouterLink>
  </section>
</template>

<style scoped>
.band {
  display: flex;
  align-items: center;
  gap: 40px;
  padding: 28px 36px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.text .form-label {
  color: var(--ink-3);
}

h2 {
  font-size: 32px;
  line-height: 1.05;
}

.lead {
  font-size: 16px;
  line-height: 1.5;
  color: var(--ink-2);
}

.counts {
  margin: 0;
  display: flex;
  gap: 36px;
}

.count {
  display: flex;
  flex-direction: column-reverse;
  gap: 2px;
}

dt {
  font-size: 14px;
  color: var(--ink-2);
}

dd {
  margin: 0;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 40px;
  line-height: 1;
}

.in-progress dd {
  color: var(--seal);
}

.cancelled dt,
.cancelled dd {
  color: var(--ink-3);
}

.cancelled dd {
  font-weight: 500;
}

.btn {
  white-space: nowrap;
}
</style>

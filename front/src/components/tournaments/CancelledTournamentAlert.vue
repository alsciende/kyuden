<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { unregisterFromTournament } from '@/api/client'
import { inverted } from '@/lib/page'
import { session } from '@/lib/session'
import { refreshTournamentEntries, tournamentEntries } from '@/lib/tournamentEntries'
import { dayLabel, viewerTimeZone } from '@/lib/tournaments'

/**
 * Under the header, on every page but those of a game, each cancelled tournament the user is still registered in,
 * until they unregister. Reads their registrations again on every page.
 */
const route = useRoute()

watch(
  [() => route.name, () => session.value?.token],
  ([, token]) => void refreshTournamentEntries(token && session.value?.username ? token : null),
  { immediate: true },
)

const cancelled = computed(() => tournamentEntries.value.filter((entry) => entry.tournament.status === 'cancelled'))
const timeZone = viewerTimeZone()
const leaving = ref<string | null>(null)
const error = ref<string | null>(null)

async function unregister(tournamentId: string): Promise<void> {
  const token = session.value?.token
  if (!token) {
    return
  }
  leaving.value = tournamentId
  error.value = null
  try {
    await unregisterFromTournament(token, tournamentId)
  } catch (problem) {
    error.value = problem instanceof Error ? problem.message : 'You could not unregister.'
  } finally {
    leaving.value = null
    await refreshTournamentEntries(token)
  }
}
</script>

<template>
  <template v-if="!inverted">
    <div v-for="entry in cancelled" :key="entry.tournament.id" role="alert" class="cancelled">
      <svg class="warning" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
        <path d="M12 9v4M12 17h.01" />
      </svg>
      <span class="text">
        <strong>The tournament of {{ dayLabel(entry.tournament.starts_at, timeZone) }} is cancelled.</strong>
        <span class="more"> Its organizer cancelled it; you are still registered.</span>
        <span v-if="error && leaving === null" class="form-error"> {{ error }}</span>
      </span>
      <RouterLink :to="{ name: 'tournament', params: { id: entry.tournament.id } }" class="see">See it</RouterLink>
      <button
        type="button"
        class="btn btn-ghost"
        :disabled="leaving === entry.tournament.id"
        @click="unregister(entry.tournament.id)"
      >
        Unregister
      </button>
    </div>
  </template>
</template>

<style scoped>
.cancelled {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 48px;
  background: var(--selected);
  border-bottom: 1.5px solid var(--seal);
  font-size: 16px;
}

.warning {
  flex: none;
  stroke: var(--seal);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.text {
  flex: 1;
  min-width: 0;
}

.more {
  color: var(--ink-2);
}

.see {
  font-size: 15px;
  font-weight: 600;
}

.btn {
  min-height: 40px;
  padding: 0 18px;
  font-size: 15px;
}
</style>

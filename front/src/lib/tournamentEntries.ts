import { ref } from 'vue'

import { fetchTournamentEntries, type TournamentEntry } from '@/api/client'

/**
 * The registrations of the account in the tournaments that have not ended, cancelled ones included: read again on
 * every page by the alert of the cancelled tournaments (`CancelledTournamentAlert.vue`), and once the user registers,
 * changes their deck or unregisters, so that the list, the page of a tournament and the alert agree.
 */
export const tournamentEntries = ref<TournamentEntry[]>([])

let reading = 0

/** reads them again (none without a token: a visitor, a guest), a later read winning */
export async function refreshTournamentEntries(token: string | null): Promise<void> {
  const current = ++reading
  if (token === null) {
    tournamentEntries.value = []

    return
  }
  try {
    const entries = await fetchTournamentEntries(token)
    if (current === reading) {
      tournamentEntries.value = entries
    }
  } catch {
    // kept as they were: the next page reads them again
  }
}

/** the registration of the user in a tournament, if any */
export function entryIn(tournamentId: string): TournamentEntry | undefined {
  return tournamentEntries.value.find((entry) => entry.tournament.id === tournamentId)
}

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { ApiProblem, fetchCards, fetchOwnedDecks, importDeck, type Card, type OwnedDeck } from '@/api/client'
import ClanMon from '@/components/ClanMon.vue'
import { deckName, deckRows, type DeckRow } from '@/lib/decks'
import { importProblem, type ImportProblem } from '@/lib/queue'
import { session } from '@/lib/session'

/**
 * The decks of the user, the last played first, with their record in short; a deck of EmeraldDB is imported from the
 * URL of its page, below them.
 */

/** null while the decks are read */
const rows = ref<DeckRow[] | null>(null)
const error = ref<string | null>(null)
let cards: Record<string, Card> = {}
/** the deck just imported, marked in the list */
const imported = ref<string | null>(null)
const deckUrl = ref('')
const importing = ref(false)
const importFailure = ref<ImportProblem | null>(null)
const notice = ref<{ before: string; name: string; after: string } | null>(null)

function show(decks: OwnedDeck[]): void {
  const list = deckRows(decks, cards)
  // the deck just imported first
  rows.value =
    imported.value === null
      ? list
      : [...list].sort((a, b) => Number(b.id === imported.value) - Number(a.id === imported.value))
}

onMounted(async () => {
  const token = session.value?.token
  if (!token) {
    return
  }
  try {
    const [decks, catalogue] = await Promise.all([fetchOwnedDecks(token), fetchCards()])
    cards = catalogue
    show(decks)
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'Your decks cannot be read.'
  }
})

/**
 * Imports the deck of the URL, which comes first in the list; a deck the user already has is told.
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
    const alreadyOwned = rows.value?.some((row) => row.id === deck.id) ?? false
    imported.value = deck.id
    show(await fetchOwnedDecks(token))
    const name = deckName(deck.name)
    notice.value = alreadyOwned
      ? { before: 'You already have ', name, after: '.' }
      : { before: '', name, after: ' imported from EmeraldDB.' }
    deckUrl.value = ''
  } catch (problem) {
    importFailure.value =
      problem instanceof ApiProblem ? importProblem(problem) : { summary: 'The deck cannot be imported.', details: [] }
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div class="decks screen">
    <div class="heading">
      <div class="eyebrow">Your decks</div>
      <h1>Decks</h1>
      <p class="lead">The decks you have imported. Open one to read its cards and how it has fared.</p>
    </div>

    <section class="list" aria-label="Your decks">
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <p v-else-if="rows !== null && rows.length === 0" class="empty">You have no deck yet: import one below.</p>
      <template v-else-if="rows !== null">
        <div class="columns" aria-hidden="true">
          <span />
          <span class="form-label">Deck</span>
          <span class="form-label number">Played</span>
          <span class="form-label number">Won</span>
          <span class="form-label number">Win rate</span>
          <span class="form-label">Last played</span>
          <span />
        </div>
        <ul>
          <li v-for="row in rows" :key="row.id">
            <RouterLink :to="{ name: 'deck', params: { id: row.id } }" class="deck">
              <span class="mon">
                <ClanMon v-if="row.clan" :src="row.clan.mon" :color="row.clan.color" :size="40" />
              </span>
              <span class="title">
                <span class="name">
                  {{ row.name }}
                  <span v-if="row.id === imported" class="tag">New</span>
                </span>
                <span class="stronghold">{{ row.stronghold }}</span>
              </span>
              <span class="number">{{ row.played }}</span>
              <span class="number">{{ row.won }}</span>
              <span class="number rate">{{ row.rate }}</span>
              <span class="last">{{ row.lastPlayed }}</span>
              <span class="open">Open</span>
            </RouterLink>
          </li>
        </ul>
      </template>
    </section>

    <form class="import" novalidate @submit.prevent="importFromUrl">
      <label for="deck-url" class="import-label">Import a deck from EmeraldDB</label>
      <div class="import-row">
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
      </div>
      <div v-if="importFailure !== null" id="import-problem" class="form-alert" role="alert">
        <p class="form-alert-title">{{ importFailure.summary }}</p>
        <p v-for="detail in importFailure.details" :key="detail" class="form-alert-text">{{ detail }}</p>
      </div>
      <p v-if="notice !== null" class="notice" role="status">
        {{ notice.before }}<strong>{{ notice.name }}</strong
        >{{ notice.after }}
      </p>
    </form>
  </div>
</template>

<style scoped>
.decks {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 36px;
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 52px 48px 48px;
  box-sizing: border-box;
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
  max-width: 640px;
  font-size: 16px;
  color: var(--ink-2);
}

.list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

.list ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.columns,
.deck {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) 90px 90px 90px 130px 60px;
  align-items: center;
  gap: 20px;
}

.columns {
  padding: 0 21px;
}

.columns .form-label {
  color: var(--ink-3);
}

.deck {
  min-height: 76px;
  padding: 0 20px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
  color: var(--ink);
  text-decoration: none;
}

@media (hover: hover) {
  .deck:hover {
    border-color: var(--ink);
  }
}

.mon {
  display: flex;
}

.title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.name {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font-display);
  font-size: 21px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tag {
  flex: none;
  font-family: var(--font-ui);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--seal);
  border: 1px solid var(--seal);
  border-radius: 2px;
  padding: 2px 6px;
}

.stronghold,
.last {
  font-size: 14px;
  color: var(--ink-2);
}

.number {
  text-align: right;
  font-size: 17px;
  font-variant-numeric: tabular-nums;
}

.rate {
  font-weight: 600;
}

.open {
  text-align: right;
  font-size: 15px;
  font-weight: 600;
  color: var(--seal);
}

.empty {
  font-size: 17px;
  color: var(--ink-2);
}

.import {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 20px 24px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.import-label {
  font-size: 15px;
  font-weight: 600;
}

.import-row {
  display: flex;
  gap: 12px;
}

.import-row .field {
  flex: 1;
}

.notice {
  font-size: 14px;
  color: var(--ink-2);
}

.notice strong {
  font-family: var(--font-display);
  font-size: 16px;
}
</style>

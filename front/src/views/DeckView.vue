<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiProblem, deleteDeck, fetchCards, fetchOwnedDeck, renameDeck, type Card, type OwnedDeck } from '@/api/client'
import ClanMon from '@/components/ClanMon.vue'
import CardPreview from '@/components/game/CardPreview.vue'
import { findClan } from '@/data/clans'
import { deckContents, deckName, endingRows, opponentRows, recordTiles } from '@/lib/decks'
import { ELEMENT_ICONS, ELEMENT_NAMES } from '@/lib/icons'
import { hidePreview, showPreview } from '@/lib/preview'
import { session } from '@/lib/session'
import type { Element } from '@/api/game'

/**
 * A deck of the user: its record (played, won, lost, how the games ended, against which clans) and its cards by
 * their titles, the image of a card shown under the pointer; the user renames it in place, deletes it once confirmed,
 * or plays with it (the queue, the deck chosen).
 */
const route = useRoute()
const router = useRouter()
const deckId = String(route.params.id)

/** null while the deck is read */
const owned = ref<OwnedDeck | null>(null)
const cards = ref<Record<string, Card>>({})
const error = ref<string | null>(null)
const notFound = ref(false)

const renaming = ref(false)
const newName = ref('')
const saving = ref(false)
const renameError = ref<string | null>(null)
const nameField = useTemplateRef<HTMLInputElement>('nameField')

const confirmingDeletion = ref(false)
const deleting = ref(false)
const deleteError = ref<string | null>(null)

const name = computed(() => deckName(owned.value?.deck.name ?? null))
const clan = computed(() => findClan(owned.value?.deck.clan))
const contents = computed(() => (owned.value === null ? null : deckContents(owned.value.deck.cards, cards.value)))
const tiles = computed(() => (owned.value === null ? [] : recordTiles(owned.value.record)))
const endings = computed(() => (owned.value === null ? [] : endingRows(owned.value.record)))
const opponents = computed(() => (owned.value === null ? [] : opponentRows(owned.value.record)))
const strongholdImage = computed(() =>
  contents.value?.stronghold ? (cards.value[contents.value.stronghold.code]?.image_url ?? null) : null,
)
const subtitle = computed(() =>
  [clan.value ? `${clan.value.name} Clan` : null, contents.value?.stronghold?.name, contents.value?.role?.name]
    .filter((part) => part)
    .join(' · '),
)
const counts = computed(() => {
  if (contents.value === null) {
    return ''
  }
  const [dynasty, conflict] = contents.value.sides

  return `${dynasty?.count ?? 0} dynasty · ${conflict?.count ?? 0} conflict · ${contents.value.provinces.length} provinces`
})
/** the right edge of the room of the image of a card */
const windowWidth = ref(window.innerWidth)
const onResize = () => (windowWidth.value = window.innerWidth)

onMounted(async () => {
  window.addEventListener('resize', onResize)
  const token = session.value?.token
  if (!token) {
    return
  }
  try {
    const [deck, catalogue] = await Promise.all([fetchOwnedDeck(token, deckId), fetchCards()])
    owned.value = deck
    cards.value = catalogue
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.status === 404) {
      notFound.value = true
    } else {
      error.value = problem instanceof ApiProblem ? problem.detail : 'The deck cannot be read.'
    }
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  hidePreview()
})

function elementIcon(element: string): string {
  return ELEMENT_ICONS[element as Element] ?? ''
}

function elementName(element: string): string {
  return ELEMENT_NAMES[element as Element] ?? element
}

function preview(code: string, event: Event): void {
  showPreview(code, event.currentTarget as HTMLElement, false)
}

async function startRenaming(): Promise<void> {
  newName.value = owned.value?.deck.name ?? ''
  renameError.value = null
  confirmingDeletion.value = false
  renaming.value = true
  await nextTick()
  nameField.value?.focus()
  nameField.value?.select()
}

function cancelRenaming(): void {
  renaming.value = false
  renameError.value = null
}

async function saveName(): Promise<void> {
  const token = session.value?.token
  const value = newName.value.trim()
  if (!token || owned.value === null || saving.value) {
    return
  }
  if (value === '') {
    renameError.value = 'The name cannot be empty.'

    return
  }
  saving.value = true
  renameError.value = null
  try {
    const deck = await renameDeck(token, deckId, value)
    owned.value = { ...owned.value, deck }
    renaming.value = false
  } catch (problem) {
    renameError.value =
      problem instanceof ApiProblem ? (problem.violations[0]?.message ?? problem.detail) : 'The deck cannot be renamed.'
  } finally {
    saving.value = false
  }
}

async function removeDeck(): Promise<void> {
  const token = session.value?.token
  if (!token || deleting.value) {
    return
  }
  deleting.value = true
  deleteError.value = null
  try {
    await deleteDeck(token, deckId)
    await router.push({ name: 'decks' })
  } catch (problem) {
    deleteError.value = problem instanceof ApiProblem ? problem.detail : 'The deck cannot be deleted.'
    confirmingDeletion.value = false
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="deck-page screen">
    <template v-if="notFound">
      <h1>Deck not found</h1>
      <p class="lead">This deck is not one of yours, or it was deleted.</p>
      <RouterLink :to="{ name: 'decks' }" class="btn-link back">Your decks</RouterLink>
    </template>
    <p v-else-if="error" class="form-error" role="alert">{{ error }}</p>
    <template v-else-if="owned !== null && contents !== null">
      <div class="heading">
        <div class="identity">
          <ClanMon v-if="clan" :src="clan.mon" :color="clan.color" :size="88" />
          <div class="heading-text">
            <div class="eyebrow">{{ subtitle }}</div>
            <form v-if="renaming" class="rename" novalidate @submit.prevent="saveName">
              <label for="deck-name" class="visually-hidden">Name of the deck</label>
              <input
                id="deck-name"
                ref="nameField"
                v-model="newName"
                class="field name-field"
                maxlength="255"
                :aria-invalid="renameError !== null"
                :aria-describedby="renameError !== null ? 'rename-error' : undefined"
                @keydown.esc.prevent="cancelRenaming"
              />
              <button type="submit" class="btn btn-primary" :disabled="saving">Save</button>
              <button type="button" class="btn-link" @click="cancelRenaming">Cancel</button>
            </form>
            <h1 v-else>{{ name }}</h1>
            <p v-if="renameError" id="rename-error" class="form-error" role="alert">{{ renameError }}</p>
            <div class="counts">{{ counts }}</div>
            <div v-if="confirmingDeletion" class="confirm" role="group" aria-labelledby="confirm-title">
              <div class="confirm-text">
                <span id="confirm-title" class="confirm-title">Delete {{ name }}?</span>
                <span class="confirm-detail">The games played with it stay in your history.</span>
              </div>
              <button type="button" class="btn btn-seal" :disabled="deleting" @click="removeDeck">Delete</button>
              <button type="button" class="btn-link" @click="confirmingDeletion = false">Keep it</button>
            </div>
            <div v-else-if="!renaming" class="manage">
              <button type="button" class="btn-link" @click="startRenaming">Rename</button>
              <button type="button" class="btn-link delete" @click="confirmingDeletion = true">Delete</button>
            </div>
            <p v-if="deleteError" class="form-error" role="alert">{{ deleteError }}</p>
          </div>
        </div>
        <div class="actions">
          <RouterLink :to="{ name: 'decks' }" class="btn-link">Back</RouterLink>
          <RouterLink :to="{ name: 'queue', query: { deck: deckId } }" class="btn btn-primary">Play with it</RouterLink>
        </div>
      </div>

      <section class="record" aria-labelledby="record-title">
        <h2 id="record-title">Record</h2>
        <div class="tiles">
          <div v-for="tile in tiles" :key="tile.label" class="tile">
            <span class="form-label">{{ tile.label }}</span>
            <span class="tile-value">{{ tile.value }}</span>
            <span class="tile-detail">{{ tile.detail }}</span>
          </div>
        </div>
        <div v-if="endings.length > 0 || opponents.length > 0" class="breakdowns">
          <table class="breakdown">
            <caption class="form-label">
              How the games ended
            </caption>
            <thead>
              <tr>
                <th scope="col"><span class="visually-hidden">Victory condition</span></th>
                <th scope="col">Won</th>
                <th scope="col">Lost</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ending in endings" :key="ending.label">
                <th scope="row">{{ ending.label }}</th>
                <td>{{ ending.won }}</td>
                <td>{{ ending.lost }}</td>
              </tr>
            </tbody>
          </table>
          <table class="breakdown">
            <caption class="form-label">
              Against each clan
            </caption>
            <thead>
              <tr>
                <th scope="col"><span class="visually-hidden">Clan</span></th>
                <th scope="col">Won</th>
                <th scope="col">Lost</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="opponent in opponents" :key="opponent.clan.id">
                <th scope="row">
                  <span class="clan">
                    <ClanMon :src="opponent.clan.mon" :color="opponent.clan.color" :size="18" />
                    {{ opponent.clan.name }}
                  </span>
                </th>
                <td>{{ opponent.won }}</td>
                <td>{{ opponent.lost }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="cards" aria-labelledby="cards-title">
        <h2 id="cards-title">Cards</h2>
        <div class="fixed">
          <div class="stronghold">
            <img
              v-if="strongholdImage"
              class="stronghold-image"
              :src="strongholdImage"
              :alt="contents.stronghold?.name ?? ''"
            />
            <div class="group">
              <h3 class="form-label">Stronghold &amp; role</h3>
              <ul>
                <li
                  v-for="card in [contents.stronghold, contents.role].filter((card) => card !== null)"
                  :key="card.code"
                >
                  <span class="card-name" @mouseenter="preview(card.code, $event)" @mouseleave="hidePreview">
                    {{ card.name }}
                  </span>
                </li>
              </ul>
            </div>
          </div>
          <div class="group">
            <h3 class="form-label">Provinces ({{ contents.provinces.length }})</h3>
            <ul>
              <li v-for="province in contents.provinces" :key="province.code" class="province">
                <span class="card-name" @mouseenter="preview(province.code, $event)" @mouseleave="hidePreview">
                  {{ province.name }}
                </span>
                <span
                  v-for="element in province.elements"
                  :key="element"
                  class="icon element has-tip"
                  :title="elementName(element)"
                  role="img"
                  :aria-label="elementName(element)"
                  >{{ elementIcon(element) }}</span
                >
              </li>
            </ul>
          </div>
        </div>
        <div class="sides">
          <div v-for="side in contents.sides" :key="side.title" class="side">
            <h3>{{ side.title }} ({{ side.count }})</h3>
            <div v-for="group in side.groups" :key="group.title" class="group">
              <h4 class="form-label">{{ group.title }} ({{ group.count }})</h4>
              <ul>
                <li v-for="card in group.cards" :key="card.code" class="card">
                  <span class="quantity">{{ card.quantity }}×</span>
                  <span class="card-name" @mouseenter="preview(card.code, $event)" @mouseleave="hidePreview">
                    {{ card.name }}
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>
      <CardPreview :cards="cards" :board-right="windowWidth" />
    </template>
  </div>
</template>

<style scoped>
.deck-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 44px;
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 52px 48px 64px;
  box-sizing: border-box;
}

.heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
}

.identity {
  display: flex;
  align-items: center;
  gap: 24px;
  min-width: 0;
}

.heading-text {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

h1 {
  font-size: 56px;
  line-height: 1;
  overflow-wrap: anywhere;
}

.lead,
.counts {
  font-size: 14px;
  color: var(--ink-2);
}

.lead {
  font-size: 16px;
}

.back {
  align-self: flex-start;
}

.rename {
  display: flex;
  align-items: center;
  gap: 12px;
}

.name-field {
  width: min(560px, 50vw);
  font-family: var(--font-display);
  font-size: 36px;
  font-weight: 700;
}

.manage {
  display: flex;
  gap: 20px;
}

.manage .btn-link {
  padding: 8px 0;
}

.delete {
  color: var(--seal);
}

.confirm {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 4px;
  padding: 14px 20px;
  background: var(--selected);
  border: 1.5px solid var(--seal);
  border-radius: 2px;
}

.confirm-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.confirm-title {
  font-size: 15px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.confirm-detail {
  font-size: 14px;
  color: var(--ink-2);
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

.actions {
  flex: none;
  display: flex;
  align-items: center;
  gap: 16px;
}

h2 {
  font-size: 30px;
}

.record,
.cards {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 18px 20px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.tile .form-label {
  color: var(--ink-3);
}

.tile-value {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.tile-detail {
  min-height: 1.3em;
  font-size: 13px;
  color: var(--ink-2);
}

.breakdowns {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 40px;
  align-items: start;
}

.breakdown {
  width: 100%;
  border-collapse: collapse;
  font-size: 15px;
  font-variant-numeric: tabular-nums;
}

.breakdown caption {
  text-align: left;
  padding-bottom: 10px;
  color: var(--ink-3);
}

.breakdown thead th {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-3);
  border-bottom: 1px solid var(--line);
  padding-bottom: 6px;
}

.breakdown th,
.breakdown td {
  padding: 5px 0;
  text-align: right;
  width: 70px;
}

.breakdown th[scope='row'],
.breakdown thead th:first-child {
  text-align: left;
  font-weight: 400;
  width: auto;
}

.clan {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.fixed,
.sides {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 40px;
}

.fixed {
  padding-bottom: 24px;
  border-bottom: 1px solid var(--line);
}

.stronghold {
  display: flex;
  gap: 24px;
}

.stronghold-image {
  flex: none;
  width: 160px;
  /* the corners of a card, as the image under the pointer (CardPreview.vue) */
  border-radius: 4.76% / 3.41%;
  background: var(--paper-2);
}

.group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.group .form-label {
  margin-bottom: 6px;
  font-family: var(--font-ui);
  color: var(--ink-3);
}

.group ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 15px;
}

.side {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.side h3 {
  font-size: 24px;
}

.card {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
}

.quantity {
  color: var(--ink-3);
  font-variant-numeric: tabular-nums;
}

.card-name {
  justify-self: start;
  cursor: default;
}

@media (hover: hover) {
  .card-name:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
  }
}

.province {
  display: flex;
  align-items: center;
  gap: 8px;
}

.element {
  font-size: 17px;
  color: var(--ink-2);
}
</style>

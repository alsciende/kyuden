<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { fetchCard, type Card } from '@/api/client'
import ArrowIcon from '@/components/ArrowIcon.vue'
import ClanMon from '@/components/ClanMon.vue'
import StepTrail from '@/components/StepTrail.vue'
import { CLANS, findClan } from '@/data/clans'
import { drawCourtName, MAX_NAME_LENGTH } from '@/lib/names'
import { newcomer } from '@/lib/newcomer'

const router = useRouter()

// the route requires a clan
const clan = computed(() => findClan(newcomer.clanId) ?? CLANS[0]!)

if (newcomer.name === null || newcomer.nameClanId !== clan.value.id) {
  newcomer.name = drawCourtName(clan.value)
  newcomer.nameClanId = clan.value.id
}

const name = computed({
  get: () => newcomer.name ?? '',
  set: (value: string) => (newcomer.name = value),
})
const displayName = computed(() => name.value.trim())
const family = computed(() => displayName.value.split(/\s+/)[0] ?? '')

const stronghold = ref<Card | null>(null)
const champion = ref<Card | null>(null)

onMounted(() => {
  fetchCard(clan.value.strongholdCode)
    .then((card) => (stronghold.value = card))
    .catch(() => {})
  fetchCard(clan.value.championCode)
    .then((card) => (champion.value = card))
    .catch(() => {})
})

function drawAnother(): void {
  newcomer.name = drawCourtName(clan.value)
}

function findOpponent(): void {
  if (displayName.value === '') {
    return
  }
  newcomer.name = displayName.value
  newcomer.game = null
  void router.push({ name: 'opponent' })
}
</script>

<template>
  <StepTrail :current="1" />
  <div class="identity screen">
    <div class="mon">
      <ClanMon :src="clan.mon" :color="clan.color" :size="340" :label="`${clan.name} Clan mon`" />
    </div>
    <form class="form" @submit.prevent="findOpponent">
      <div class="eyebrow">
        {{ clan.name }} Clan<template v-if="family"> · {{ family }} family</template>
      </div>
      <h1>{{ displayName || 'Nameless samurai' }}</h1>
      <p class="lead">
        This is how the court will know you: family name first, as is the custom in Rokugan. Keep it, draw another, or
        write your own.
      </p>
      <div class="field-group">
        <label for="court-name">Name at court</label>
        <div class="field-row">
          <input
            id="court-name"
            v-model="name"
            class="field"
            :maxlength="MAX_NAME_LENGTH"
            autocomplete="off"
            spellcheck="false"
            required
          />
          <button type="button" class="btn btn-ghost" @click="drawAnother">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M3 12a9 9 0 0 1 15.3-6.4L21 8" />
              <path d="M21 3v5h-5" />
              <path d="M21 12a9 9 0 0 1-15.3 6.4L3 16" />
              <path d="M3 21v-5h5" />
            </svg>
            Draw another
          </button>
        </div>
      </div>
      <div class="deck">
        <img v-if="stronghold?.image_url" :src="stronghold.image_url" alt="" />
        <div v-else class="deck-placeholder" />
        <div>
          <strong>Your deck: {{ clan.name }} starter deck</strong>
          <span v-if="stronghold && champion">{{ stronghold.name }} · led by {{ champion.name }}</span>
        </div>
      </div>
      <div class="actions">
        <button type="submit" class="btn btn-primary btn-large" :disabled="displayName === ''">
          Find an opponent
          <ArrowIcon />
        </button>
        <RouterLink to="/" class="btn-link">Choose another clan</RouterLink>
      </div>
    </form>
  </div>
</template>

<style scoped>
.identity {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
  gap: 72px;
  align-items: center;
  padding: 40px 96px 56px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
}

.mon {
  display: flex;
  justify-content: center;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 26px;
  min-width: 0;
}

h1 {
  font-size: 72px;
  line-height: 1;
  overflow-wrap: anywhere;
}

.lead {
  font-size: 18px;
  line-height: 1.6;
  color: var(--ink-2);
  max-width: 560px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 560px;
}

label {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.field-row {
  display: flex;
  gap: 10px;
}

.field-row .btn {
  flex: none;
}

.deck {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  background: var(--paper-2);
  border-radius: 2px;
  max-width: 560px;
}

.deck img,
.deck-placeholder {
  width: 54px;
  aspect-ratio: 300 / 419;
  object-fit: cover;
  border-radius: 4px;
}

.deck-placeholder {
  background: var(--paper-3);
}

.deck div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.deck strong {
  font-size: 16px;
}

.deck span {
  font-size: 14px;
  color: var(--ink-2);
}

.actions {
  display: flex;
  gap: 14px;
  align-items: center;
  padding-top: 6px;
}
</style>

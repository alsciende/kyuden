<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'

import { fetchCard, type Card } from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ArrowIcon from '@/components/ArrowIcon.vue'
import ClanMon from '@/components/ClanMon.vue'
import StepTrail from '@/components/StepTrail.vue'
import { CLANS, findClan, type ClanId } from '@/data/clans'
import { newcomer } from '@/lib/newcomer'

const router = useRouter()

const selected = computed(() => findClan(newcomer.clanId))

/** the stronghold and the champion of each clan, by code, read from the API */
const cards = reactive<Record<string, Card>>({})

const showcase = computed(() =>
  selected.value
    ? [
        { label: 'Stronghold', card: cards[selected.value.strongholdCode] },
        { label: 'Champion', card: cards[selected.value.championCode] },
      ]
    : [],
)

onMounted(() => {
  for (const clan of CLANS) {
    for (const code of [clan.strongholdCode, clan.championCode]) {
      fetchCard(code)
        .then((card) => (cards[code] = card))
        .catch(() => {
          // the card is shown as a blank
        })
    }
  }
  window.addEventListener('keydown', browse)
})

onBeforeUnmount(() => window.removeEventListener('keydown', browse))

const clanButtons = useTemplateRef<HTMLButtonElement[]>('clanButtons')

/** the arrow keys go through the clans, from the first or the last when none is picked */
function browse(event: KeyboardEvent): void {
  const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key]
  if (step === undefined || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
    return
  }
  const target = event.target
  if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, select'))) {
    return
  }
  event.preventDefault()

  const index = CLANS.findIndex((clan) => clan.id === newcomer.clanId)
  const next = index === -1 ? (step > 0 ? 0 : CLANS.length - 1) : (index + step + CLANS.length) % CLANS.length
  newcomer.clanId = CLANS[next]!.id

  // the focus follows the selection, so that Enter does not pick the clan left
  const buttons = clanButtons.value ?? []
  if (buttons.some((button) => button === document.activeElement)) {
    void nextTick(() => buttons[next]?.focus())
  }
}

function toggle(id: ClanId): void {
  newcomer.clanId = newcomer.clanId === id ? null : id
}

function play(): void {
  if (selected.value) {
    void router.push({ name: 'name' })
  }
}
</script>

<template>
  <StepTrail :current="0" />
  <div class="landing screen">
    <section class="intro">
      <div class="headline">
        <div class="eyebrow">Legend of the Five Rings · Living Card Game</div>
        <h1>Choose your clan.<br />Take your seat at court.</h1>
        <p class="lead">
          Play in your browser with a starter deck and guidance at every step. No account, no deck to build: you are at
          the table in under a minute.
        </p>
      </div>

      <div role="group" aria-label="The seven Great Clans" class="clans">
        <button
          v-for="clan in CLANS"
          :key="clan.id"
          ref="clanButtons"
          type="button"
          class="clan"
          :class="{ 'is-selected': clan.id === newcomer.clanId }"
          :style="{ '--clan-color': clan.color }"
          :aria-pressed="clan.id === newcomer.clanId"
          @click="toggle(clan.id)"
        >
          <ClanMon :src="clan.mon" color="var(--mon-color)" :size="72" />
          <span class="clan-name">{{ clan.name }}</span>
          <span class="clan-kanji" aria-hidden="true">{{ clan.kanji }}</span>
        </button>
      </div>

      <ol class="about">
        <li>
          <span class="numeral" aria-hidden="true">一</span>
          <div>
            <strong>Play against a bot</strong>
            <span>An introductory game with your clan’s starter deck, explained step by step.</span>
          </div>
        </li>
        <li>
          <span class="numeral" aria-hidden="true">二</span>
          <div>
            <strong>Bring your own deck</strong>
            <span>Then create an account and build your deck, or import it from EmeraldDB.</span>
          </div>
        </li>
        <li>
          <span class="numeral" aria-hidden="true">三</span>
          <div>
            <strong>Join the community</strong>
            <span>Face real opponents from a community of players, whenever you like.</span>
          </div>
        </li>
      </ol>
    </section>

    <aside class="panel" aria-live="polite">
      <div v-if="!selected" class="panel-empty">
        <ClanMon :src="enso" :size="260" />
        <h2>The seven Great Clans</h2>
        <p>Each clan plays its own way. Pick one to see its starter deck.</p>
        <p class="hint">Browse them with <kbd>←</kbd> <kbd>→</kbd></p>
      </div>
      <template v-else>
        <div :key="selected.id" class="panel-clan screen">
          <div class="panel-title">
            <ClanMon :src="selected.mon" :color="selected.color" :size="104" />
            <div>
              <h2>The {{ selected.name }} Clan</h2>
              <div class="motto">{{ selected.motto }}</div>
            </div>
          </div>
          <p class="style">{{ selected.style }}</p>
          <div class="cards">
            <figure v-for="item in showcase" :key="item.label">
              <img v-if="item.card?.image_url" :src="item.card.image_url" :alt="item.card.name" class="card-img" />
              <div v-else class="card-img card-placeholder" />
              <figcaption>
                <span>{{ item.label }}</span
                >{{ item.card?.name }}
              </figcaption>
            </figure>
          </div>
          <button type="button" class="btn btn-primary btn-large play" @click="play">
            Play as the {{ selected.name }} Clan
            <ArrowIcon />
          </button>
        </div>
      </template>
    </aside>
  </div>
</template>

<style scoped>
.landing {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
  gap: 56px;
  padding: 40px 48px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
}

.intro {
  display: flex;
  flex-direction: column;
  gap: 36px;
  min-width: 0;
}

.headline {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

h1 {
  font-size: 60px;
  line-height: 1.08;
}

.lead {
  font-size: 19px;
  line-height: 1.6;
  color: var(--ink-2);
  max-width: 620px;
}

.clans {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
}

.clan {
  --mon-color: var(--ink);
  font: inherit;
  color: inherit;
  background: transparent;
  border: 1.5px solid transparent;
  border-radius: 2px;
  padding: 18px 6px 14px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  transition:
    background 0.15s,
    border-color 0.15s;
}

.clan:hover,
.clan.is-selected {
  --mon-color: var(--clan-color);
  background: var(--paper-2);
}

.clan.is-selected {
  border-color: var(--ink);
}

.clan-name {
  font-family: var(--font-display);
  font-size: 18px;
  font-weight: 700;
}

.clan-kanji {
  font-family: var(--font-display);
  font-size: 13px;
  color: var(--ink-3);
}

.about {
  list-style: none;
  margin: auto 0 0;
  padding: 28px 0 0;
  border-top: 1px solid var(--line);
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
}

.about li {
  display: flex;
  gap: 14px;
}

.about div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.about strong {
  font-size: 16px;
}

.about span:not(.numeral) {
  font-size: 15px;
  line-height: 1.5;
  color: var(--ink-2);
}

.numeral {
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 700;
  color: var(--seal);
  line-height: 1;
}

.panel {
  background: var(--paper-2);
  border-radius: 2px;
  padding: 36px 36px 32px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 640px;
}

.panel-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 22px;
}

.panel-empty h2 {
  font-size: 32px;
}

.panel-empty p {
  font-size: 17px;
  line-height: 1.6;
  color: var(--ink-2);
  max-width: 360px;
}

.panel-empty .hint {
  font-size: 14px;
  color: var(--ink-3);
}

kbd {
  font-family: var(--font-ui);
  font-size: 13px;
  padding: 1px 6px;
  border: 1px solid var(--line);
  border-radius: 2px;
  background: var(--paper);
}

.panel-clan {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 20px;
}

.panel-title > div {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.panel-title h2 {
  font-size: 36px;
  line-height: 1.1;
}

.motto {
  font-family: var(--font-display);
  font-size: 17px;
  color: var(--ink-2);
}

.style {
  font-size: 17px;
  line-height: 1.6;
}

.cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

figure {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

figcaption {
  font-size: 13px;
  color: var(--ink-2);
  display: flex;
  flex-direction: column;
}

figcaption span {
  text-transform: uppercase;
  letter-spacing: 0.1em;
  font-weight: 700;
}

.card-img {
  width: 100%;
  aspect-ratio: 300 / 419;
  object-fit: cover;
  border-radius: 10px;
  display: block;
  box-shadow:
    0 1px 0 rgba(31, 27, 22, 0.08),
    0 10px 24px -14px rgba(31, 27, 22, 0.55);
}

.card-placeholder {
  background: var(--paper-3);
  box-shadow: none;
}

.play {
  margin-top: auto;
}
</style>

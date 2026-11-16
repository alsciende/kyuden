<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { ApiProblem, fetchTrophies } from '@/api/client'
import ClanMon from '@/components/ClanMon.vue'
import PlayerNobori from '@/components/PlayerNobori.vue'
import { monOf, patternOf, type Banner } from '@/data/nobori'
import { earnedCount, loadOwnNobori } from '@/lib/nobori'
import { session } from '@/lib/session'
import { trophyCards, type TrophyCard } from '@/lib/trophies'

/**
 * The trophies of the catalogue as the user has earned them or not (mockup `VM8ezvCHeUDffKwESAe2fE`, screen 4): each
 * with its goal and its reward drawn, once earned the day it was, which leads to the replay of the game; the others
 * dashed, the reward dimmed under a padlock. Beside the heading, the nobori of the user.
 */

/** null while the trophies are read */
const cards = ref<TrophyCard[] | null>(null)
const banner = ref<Banner | null>(null)
const error = ref<string | null>(null)

const hasAccount = computed(() => session.value !== null && session.value.username !== null)
const count = computed(() =>
  cards.value === null
    ? ''
    : earnedCount(
        cards.value.filter((card) => card.earned),
        cards.value,
      ),
)

onMounted(async () => {
  const current = session.value
  if (current === null) {
    return
  }
  try {
    const [trophies, nobori] = await Promise.all([fetchTrophies(current.token), loadOwnNobori(current.token)])
    cards.value = trophyCards(trophies)
    banner.value = nobori.banner
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'Your trophies cannot be read.'
  }
})

/** the mon of the reward, in its color once earned */
function rewardMon(card: TrophyCard): { src: string; color: string; ringed: boolean; label: string } | null {
  const id = card.rewardChange?.mon
  if (id === undefined) {
    return null
  }
  const mon = monOf(id)

  return {
    src: mon.src,
    color: card.earned ? mon.color : '#c9bea9',
    ringed: mon.ringed,
    label: `The mon of the ${mon.label}`,
  }
}

/** the pattern of the reward, a swatch */
function rewardPattern(card: TrophyCard): Banner | null {
  const id = card.rewardChange?.pattern

  return id === undefined ? null : { mon: 'enso', pattern: patternOf(id).id }
}
</script>

<template>
  <div class="trophies screen">
    <div class="heading">
      <div class="heading-text">
        <div class="eyebrow">Your trophies</div>
        <h1>Trophies</h1>
        <p class="lead">
          Each trophy marks a step at court: a way of playing found, a clan led to victory. Some give a mon or a pattern
          for your nobori.
        </p>
        <p v-if="cards" class="count">{{ count }}</p>
      </div>
      <div v-if="banner" class="own-nobori">
        <div class="own-nobori-text">
          <span class="form-label">Your nobori</span>
          <RouterLink v-if="hasAccount" :to="{ name: 'account' }" class="btn-link">Change it</RouterLink>
        </div>
        <PlayerNobori :banner="banner" :width="64" class="nobori" />
      </div>
    </div>

    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <ul v-else-if="cards" class="grid" aria-label="Trophies">
      <li v-for="card in cards" :key="card.id" class="tile" :class="{ earned: card.earned }">
        <div class="tile-top">
          <template v-if="rewardMon(card)">
            <span class="disc">
              <span v-if="rewardMon(card)!.ringed" class="ring" :style="{ borderColor: rewardMon(card)!.color }">
                <ClanMon
                  :src="rewardMon(card)!.src"
                  :color="rewardMon(card)!.color"
                  :size="32"
                  :label="rewardMon(card)!.label"
                />
              </span>
              <ClanMon
                v-else
                :src="rewardMon(card)!.src"
                :color="rewardMon(card)!.color"
                :size="38"
                :label="rewardMon(card)!.label"
              />
            </span>
          </template>
          <PlayerNobori
            v-else-if="rewardPattern(card)"
            :banner="rewardPattern(card)!"
            :width="40"
            bare
            class="swatch"
            :class="{ dimmed: !card.earned }"
          />
          <span v-else></span>
          <span v-if="card.earned" class="badge">Earned</span>
          <svg v-else class="lock" width="20" height="20" viewBox="0 0 24 24" role="img" aria-label="Not earned yet">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <h2>{{ card.name }}</h2>
        <p class="goal">{{ card.goal }}</p>
        <p v-if="card.reward" class="reward"><span class="reward-label">Reward</span> · {{ card.reward }}</p>
        <p v-if="card.earnedOn" class="earned-on">
          <RouterLink
            v-if="card.gameId"
            :to="{ name: 'replay', params: { id: card.gameId } }"
            :aria-label="`Earned ${card.earnedOn}: replay the game`"
            >{{ card.earnedOn }}</RouterLink
          >
          <span v-else>{{ card.earnedOn }}</span>
        </p>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.trophies {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding: 52px 96px 48px;
}

.heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
}

.heading-text {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

h1 {
  font-size: 64px;
  line-height: 1;
}

.lead {
  max-width: 620px;
  font-size: 16px;
  color: var(--ink-2);
}

.count {
  font-size: 16px;
  font-weight: 700;
}

.own-nobori {
  display: flex;
  align-items: flex-end;
  gap: 18px;
}

.own-nobori-text {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.own-nobori-text .form-label {
  color: var(--ink-3);
}

.nobori {
  height: 170px;
}

.grid {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px;
  border: 1px dashed var(--line);
  border-radius: 3px;
}

.tile.earned {
  background: var(--card);
  border-style: solid;
}

.tile-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
}

/* the mon on a disc of paper, like on a nobori, by day and by night */
.disc {
  width: 52px;
  height: 52px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--nobori-line);
  border-radius: 50%;
  background: var(--nobori-paper);
}

.ring {
  width: 42px;
  height: 42px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border-width: 3px;
  border-style: solid;
  border-radius: 50%;
}

.swatch {
  height: 64px;
}

.swatch.dimmed {
  opacity: 0.45;
}

.badge {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--seal-ink);
  background: var(--seal);
  border-radius: 2px;
  padding: 3px 7px;
}

.lock {
  fill: none;
  stroke: var(--ink-3);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

h2 {
  font-size: 22px;
  line-height: 1.15;
}

.tile:not(.earned) h2 {
  color: var(--ink-2);
}

.goal {
  font-size: 15px;
  line-height: 1.45;
  color: var(--ink-2);
}

.reward {
  font-size: 14px;
  line-height: 1.45;
}

.reward-label {
  color: var(--ink-3);
}

.earned-on {
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--paper-3);
  font-size: 14px;
}

.earned-on a {
  color: var(--ink);
  font-weight: 500;
  text-underline-offset: 3px;
}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { fetchTrophies } from '@/api/client'
import ClanMon from '@/components/ClanMon.vue'
import PlayerNobori from '@/components/PlayerNobori.vue'
import enso from '@/assets/mons/neutral.svg'
import type { Banner } from '@/data/nobori'
import { chooseNobori, loadOwnNobori } from '@/lib/nobori'
import { session } from '@/lib/session'
import { earnedHeading, earnedInGame, isWorn, trophyCards, withReward, type TrophyCard } from '@/lib/trophies'

/**
 * The trophies the player has earned in the game that has just ended, on the screen of its result (mockup
 * `VM8ezvCHeUDffKwESAe2fE`): each with its goal and its reward drawn on their nobori, which they raise at once. One is
 * told at length, several in a list. Nothing shows when the game gave none, or when they cannot be read.
 */
const props = defineProps<{ gameId: string }>()
const emit = defineEmits<{ earned: [count: number] }>()

const banner = ref<Banner | null>(null)
const earned = ref<TrophyCard[]>([])
const raising = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  const current = session.value
  if (current === null) {
    return
  }
  try {
    const [trophies, nobori] = await Promise.all([fetchTrophies(current.token), loadOwnNobori(current.token)])
    banner.value = nobori.banner
    earned.value = earnedInGame(trophyCards(trophies), props.gameId)
    emit('earned', earned.value.length)
  } catch {
    // the trophies stay earned: the page of the trophies shows them
  }
})

const heading = computed(() => earnedHeading(earned.value.length))

function worn(card: TrophyCard): boolean {
  return banner.value !== null && isWorn(card, banner.value)
}

/** the nobori of the player with the reward, as raised now or as it would be */
function shown(card: TrophyCard): Banner | null {
  return banner.value === null ? null : withReward(card, banner.value)
}

function capitalized(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

async function raise(card: TrophyCard): Promise<void> {
  const current = session.value
  if (current === null || card.rewardChange === null || raising.value) {
    return
  }
  raising.value = true
  error.value = null
  try {
    banner.value = (await chooseNobori(current.token, card.rewardChange)).banner
  } catch {
    error.value = 'Your nobori could not be changed.'
  } finally {
    raising.value = false
  }
}
</script>

<template>
  <section v-if="earned.length > 0" class="trophies" aria-labelledby="trophies-heading">
    <p id="trophies-heading" class="heading">{{ heading }}</p>

    <!-- one trophy, told at length -->
    <div v-if="earned.length === 1" class="single">
      <PlayerNobori v-if="shown(earned[0]!)" :banner="shown(earned[0]!)!" :width="56" class="nobori" />
      <div class="text">
        <h2>{{ earned[0]!.name }}</h2>
        <p class="goal">{{ earned[0]!.goal }}</p>
        <p v-if="earned[0]!.reward" class="reward">{{ capitalized(earned[0]!.reward) }} is yours for your nobori.</p>
        <template v-if="earned[0]!.rewardChange">
          <p v-if="worn(earned[0]!)" class="worn">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            Raised on your nobori
          </p>
          <button v-else type="button" class="raise" :disabled="raising" @click="raise(earned[0]!)">
            Raise it on your nobori
          </button>
        </template>
      </div>
    </div>

    <!-- several, in a list -->
    <ul v-else class="list">
      <li v-for="card in earned" :key="card.id">
        <PlayerNobori v-if="shown(card)" :banner="shown(card)!" :width="36" class="nobori small" />
        <ClanMon v-else :src="enso" :size="30" color="#b3a894" class="no-reward" />
        <div class="text">
          <h2>{{ card.name }}</h2>
          <p class="goal">{{ card.goal }}</p>
          <p v-if="card.reward" class="reward">{{ capitalized(card.reward) }} is yours.</p>
        </div>
        <template v-if="card.rewardChange">
          <span v-if="worn(card)" class="worn">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            Raised
          </span>
          <button
            v-else
            type="button"
            class="raise"
            :disabled="raising"
            :aria-label="`Raise ${card.reward} on your nobori`"
            @click="raise(card)"
          >
            Raise it
          </button>
        </template>
      </li>
    </ul>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.trophies {
  width: 100%;
  margin-top: 6px;
  padding-top: 18px;
  border-top: 1px solid rgba(244, 238, 226, 0.25);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.heading {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #ec6a52;
}

.single {
  display: flex;
  align-items: center;
  gap: 22px;
  text-align: left;
}

.nobori {
  height: 140px;
}

.nobori.small {
  height: 92px;
}

.text {
  display: flex;
  flex-direction: column;
  gap: 5px;
  max-width: 310px;
}

h2 {
  font-size: 28px;
  line-height: 1.1;
}

.list h2 {
  font-size: 22px;
}

/* the texts of the screen of the result, which is dark whatever the theme */
.trophies .goal {
  font-size: 15px;
  line-height: 1.45;
  color: #d8cdb8;
}

.trophies .reward {
  font-size: 15px;
  line-height: 1.45;
  color: var(--on-veil);
}

.list {
  width: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
  text-align: left;
}

.list li {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(244, 238, 226, 0.14);
}

.list li:last-child {
  border-bottom: 0;
}

.list .text {
  flex: 1;
  max-width: none;
}

.list .goal,
.list .reward {
  font-size: 14px;
}

.no-reward {
  flex: none;
  width: 36px;
  display: flex;
  justify-content: center;
}

.raise {
  align-self: flex-start;
  margin-top: 4px;
  font: inherit;
  font-size: 15px;
  font-weight: 700;
  min-height: 40px;
  padding: 0 18px;
  border-radius: 2px;
  border: 1.5px solid rgba(244, 238, 226, 0.7);
  background: transparent;
  color: var(--on-veil);
  cursor: pointer;
}

.list .raise {
  flex: none;
  align-self: center;
  margin-top: 0;
  padding: 0 14px;
  font-size: 14px;
}

@media (hover: hover) {
  .raise:hover:not(:disabled) {
    background: rgba(244, 238, 226, 0.12);
  }
}

.raise:disabled {
  opacity: 0.6;
  cursor: default;
}

.worn {
  margin-top: 4px;
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: var(--on-veil);
}

.list .worn {
  flex: none;
  margin-top: 0;
  font-size: 14px;
}

.worn svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.trophies .error {
  font-size: 14px;
  color: #ec6a52;
}
</style>

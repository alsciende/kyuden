<script setup lang="ts">
import { computed } from 'vue'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { now, waitingTexts, type Waiting } from '@/lib/queueing'
import { useLeaveQueue } from '@/lib/useLeaveQueue'

/**
 * The wait in the queue, told in full: in the tile "Play" of the home of an account (`tile`), who may watch a game
 * meanwhile, or as the whole screen of the queue for a guest, who cannot.
 */
const props = defineProps<{ waiting: Waiting; tile?: boolean }>()

const texts = computed(() => waitingTexts(props.waiting, now.value))
const { sending, error, leave } = useLeaveQueue()
</script>

<template>
  <section v-if="tile" class="tile inverted" aria-labelledby="queue-title">
    <div class="tile-top">
      <div class="eyebrow" role="status">{{ waiting.friendly === null ? 'Play · ' : '' }}{{ texts.eyebrow }}</div>
      <ClanMon :src="enso" :size="72" class="enso" />
    </div>
    <h2 id="queue-title">{{ texts.title }}</h2>
    <p class="lead">
      <template v-if="texts.deck !== null">
        You play the <strong>{{ texts.deck }}</strong
        >.
      </template>
      {{ texts.opens }}
    </p>
    <p class="meanwhile">Watch a game meanwhile: you leave it as soon as your opponent is found.</p>
    <p v-if="texts.expiry !== null" class="expiry">{{ texts.expiry }}</p>
    <div class="tile-actions">
      <button type="button" class="btn btn-ghost btn-large" :disabled="sending" @click="leave">
        {{ texts.leave }}
      </button>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    </div>
  </section>

  <div v-else class="waiting screen">
    <ClanMon :src="enso" :size="168" class="enso" />
    <div class="status" role="status">
      <div class="eyebrow">{{ texts.eyebrow }}</div>
      <h1>{{ texts.title }}</h1>
      <p class="lead">
        <template v-if="texts.deck !== null">
          You play the <strong>{{ texts.deck }}</strong
          >.
        </template>
        {{ texts.opens }}
      </p>
    </div>
    <p v-if="texts.expiry !== null" class="expiry">{{ texts.expiry }}</p>
    <button type="button" class="btn btn-ghost" :disabled="sending" @click="leave">{{ texts.leave }}</button>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.tile {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 36px 36px 32px;
  background: var(--paper);
  color: var(--ink);
  border: 1.5px solid var(--paper);
  border-radius: 2px;
}

.tile-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.tile .eyebrow {
  color: var(--ink-3);
}

h2 {
  font-size: 40px;
  line-height: 1.05;
}

.lead {
  font-size: 17px;
  line-height: 1.5;
  color: var(--ink-2);
  max-width: 560px;
}

.lead strong {
  color: var(--ink);
}

.meanwhile,
.expiry {
  font-size: 15px;
  line-height: 1.5;
  color: var(--ink-3);
}

.tile-actions {
  margin-top: auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 24px;
}

.waiting {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 28px;
  padding: 40px 96px 72px;
  text-align: center;
}

.status {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.waiting .expiry {
  font-size: 14px;
}

.enso {
  animation: turn 3.2s linear infinite;
}

@media (hover: hover) {
  .btn-ghost:hover {
    color: var(--seal);
    border-color: var(--seal);
    background: transparent;
  }
}

@keyframes turn {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .enso {
    animation-duration: 12s;
  }
}
</style>

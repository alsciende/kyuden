<script setup lang="ts">
import { computed } from 'vue'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { now, waitingTexts, type Waiting } from '@/lib/queueing'
import { useLeaveQueue } from '@/lib/useLeaveQueue'

/**
 * The wait in the queue in a line, so that the user does not forget it: under the header of the pages but the home of
 * an account, or at the top of the bar on the right of a game watched or replayed (`compact`).
 */
const props = defineProps<{ waiting: Waiting; compact?: boolean }>()

const texts = computed(() => waitingTexts(props.waiting, now.value))
const { sending, error, leave } = useLeaveQueue()
</script>

<template>
  <section v-if="compact" class="compact inverted" aria-label="In the queue">
    <div class="compact-top">
      <ClanMon :src="enso" :size="22" class="enso" />
      <span class="eyebrow" role="status">{{ texts.eyebrow }}</span>
    </div>
    <div class="title">{{ texts.title }}</div>
    <div class="compact-bottom">
      <span>Your game opens as soon as it is found.</span>
      <button type="button" class="btn-link" :disabled="sending" @click="leave">{{ texts.leave }}</button>
    </div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  </section>

  <aside v-else class="banner inverted" aria-label="In the queue">
    <ClanMon :src="enso" :size="20" class="enso" />
    <span class="text" role="status">
      {{ texts.eyebrow }} · <strong class="title">{{ texts.title }}</strong
      ><template v-if="texts.deck !== null"> with the {{ texts.deck }}</template>
    </span>
    <button type="button" class="btn btn-ghost" :disabled="sending" @click="leave">{{ texts.leave }}</button>
    <span v-if="error" class="form-error" role="alert">{{ error }}</span>
  </aside>
</template>

<style scoped>
.banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 10px 48px;
  background: var(--paper);
  border-bottom: 1px solid var(--line);
  font-size: 15px;
  color: var(--ink-2);
}

.text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.banner .title {
  font-family: var(--font-display);
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.banner .btn {
  min-height: 40px;
  padding: 0 18px;
  font-size: 15px;
  white-space: nowrap;
}

.compact {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  background: var(--paper);
  color: var(--ink);
  border-radius: 2px;
}

.compact-top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.compact .eyebrow {
  font-size: 12px;
  color: var(--ink-3);
}

.compact .title {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
}

.compact-bottom {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: var(--ink-3);
}

.compact .btn-link {
  flex: none;
  font-size: 14px;
  color: var(--ink-2);
}

.enso {
  flex: none;
  animation: turn 3.2s linear infinite;
}

@media (hover: hover) {
  .banner .btn-ghost:hover {
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

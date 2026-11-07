<script setup lang="ts">
import { inject } from 'vue'

import { ABANDONMENT } from '@/lib/abandonment'

/**
 * Asks the player whether to end the game their opponent no longer plays (they win), or to wait longer for them.
 */
defineProps<{ sending: boolean }>()

const abandonment = inject(ABANDONMENT, null)
</script>

<template>
  <section v-if="abandonment?.asked.value" class="abandonment" role="alert" aria-label="Your opponent no longer plays">
    <span class="label">Your opponent no longer plays</span>
    <p>
      They have not acted for a while. End the game, which you win, or wait for them a few more minutes: you will be
      asked again.
    </p>
    <div class="choices">
      <button type="button" class="btn btn-primary" :disabled="sending" @click="abandonment.end()">End the game</button>
      <button type="button" class="btn btn-ghost" :disabled="sending" @click="abandonment.wait()">Wait</button>
    </div>
  </section>
</template>

<style scoped>
.abandonment {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1.5px solid var(--seal);
  border-radius: 2px;
  background: var(--card);
}

.label {
  color: var(--seal);
}

p {
  margin: 0;
  font-size: 14px;
  color: var(--ink-2);
}

.choices {
  display: flex;
  gap: 8px;
}

.choices .btn {
  flex: 1;
  text-transform: none;
}
</style>

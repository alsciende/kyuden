<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { session } from '@/lib/session'

const route = useRoute()

// a guest signs in like anyone without an account
const account = computed(() => (session.value?.username ? session.value : null))
</script>

<template>
  <header class="header">
    <RouterLink to="/" class="brand" aria-label="Kyūden, back to the start">
      <ClanMon :src="enso" :size="40" />
      <span class="wordmark">Kyūden</span>
    </RouterLink>
    <RouterLink v-if="account" :to="{ name: 'account' }" class="account">
      <span class="name">{{ account.name }}</span>
      <span class="label">· Account</span>
    </RouterLink>
    <div v-else-if="route.name === 'login'" class="returning">
      <span>New to Kyūden?</span>
      <RouterLink to="/" class="btn-link">Play a first game</RouterLink>
    </div>
    <div v-else-if="route.name === 'signup'" class="returning">
      <span>Already have an account?</span>
      <RouterLink :to="{ name: 'login' }" class="btn btn-ghost">Sign in</RouterLink>
    </div>
    <div v-else class="returning">
      <span>Already play on Kyūden?</span>
      <RouterLink :to="{ name: 'login' }" class="btn btn-ghost">Sign in</RouterLink>
    </div>
  </header>
</template>

<style scoped>
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 18px 48px;
  border-bottom: 1px solid var(--line);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: var(--ink);
}

.wordmark {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.returning {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink-2);
  font-size: 15px;
}

.returning .btn {
  min-height: 44px;
  padding: 0 18px;
  font-size: 15px;
}

.account {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  padding: 10px 4px;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
}

.account .name {
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 700;
  /* names can be long */
  max-width: 420px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account .label {
  color: var(--ink-3);
}
</style>

<script setup lang="ts">
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
</script>

<template>
  <header class="header">
    <RouterLink to="/" class="brand" aria-label="Kyūden, back to the start">
      <ClanMon :src="enso" :size="40" />
      <span class="wordmark">Kyūden</span>
    </RouterLink>
    <div class="returning">
      <span>Already play on Kyūden?</span>
      <!-- the sign in of the players who have an account comes later -->
      <button type="button" class="btn btn-ghost" aria-disabled="true" title="Coming soon">Sign in</button>
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
</style>

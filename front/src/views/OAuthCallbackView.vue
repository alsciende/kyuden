<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { completeOAuth, isProvider, pendingPurpose, providerName } from '@/lib/oauth'

/**
 * The page a provider sends the visitor back to (the redirect URI registered there): it completes the flow, then
 * leads to the account, or back to where the visitor started with what went wrong.
 */
const route = useRoute()
const router = useRouter()

const provider = computed(() => (isProvider(route.params.provider) ? route.params.provider : null))
const name = computed(() => (provider.value === null ? '' : providerName(provider.value)))
const purpose = pendingPurpose()

onMounted(async () => {
  if (provider.value === null) {
    await router.replace({ name: 'landing' })

    return
  }
  const result = await completeOAuth(provider.value, route.query)
  if (result.outcome === 'signed_in') {
    await router.replace({ name: 'account' })
  } else if (result.purpose === 'link') {
    await router.replace({ name: 'account', query: { oauth: result.outcome, provider: provider.value } })
  } else {
    await router.replace({ name: 'login', query: { oauth: result.outcome, provider: provider.value } })
  }
})
</script>

<template>
  <div class="callback screen">
    <ClanMon :src="enso" :size="160" class="breathe" />
    <div class="text">
      <div class="eyebrow">Back from {{ name }}</div>
      <h1 role="status">The gates open…</h1>
      <p v-if="purpose === 'link'">Linking your {{ name }} account.</p>
      <p v-else>Signing you in with your {{ name }} account.</p>
    </div>
  </div>
</template>

<style scoped>
.callback {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 28px;
  padding: 40px 96px;
}

.text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
}

h1 {
  font-size: 48px;
  line-height: 1.1;
}

p {
  font-size: 16px;
  color: var(--ink-2);
}

.breathe {
  animation: breathe 2.4s ease-in-out infinite;
}

@keyframes breathe {
  0%,
  100% {
    opacity: 0.35;
  }

  50% {
    opacity: 0.9;
  }
}

@media (prefers-reduced-motion: reduce) {
  .breathe {
    animation: none;
  }
}
</style>

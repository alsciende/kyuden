<script setup lang="ts">
import ProviderLogo from '@/components/ProviderLogo.vue'

const version = import.meta.env.VITE_KYUDEN_VERSION ?? 'dev'
// the invitation to the official Discord server, set when the web client is built: no link without it
const discordUrl = import.meta.env.VITE_DISCORD_URL || null
</script>

<template>
  <footer class="footer">
    <span>Kyūden {{ version }} is a fan-made project, not affiliated with Fantasy Flight Games.</span>
    <nav class="links">
      <RouterLink :to="{ name: 'terms' }">Terms and conditions</RouterLink>
      <RouterLink :to="{ name: 'privacy' }">Privacy</RouterLink>
      <a v-if="discordUrl" class="discord" :href="discordUrl" target="_blank" rel="noopener noreferrer">
        <ProviderLogo provider="discord" :size="16" />
        Discord
      </a>
    </nav>
  </footer>
</template>

<style scoped>
.footer {
  padding: 16px 48px;
  border-top: 1px solid var(--line);
  font-size: 13px;
  color: var(--ink-3);
  display: flex;
  justify-content: space-between;
  gap: 24px;
}

.links {
  display: flex;
  gap: 24px;
}

.footer a {
  color: var(--ink-3);
  text-underline-offset: 3px;
}

.discord {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.footer a:hover {
  color: var(--seal);
}
</style>

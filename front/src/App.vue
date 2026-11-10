<script setup lang="ts">
import { watchEffect } from 'vue'
import { useRoute } from 'vue-router'

import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import GameInProgressBanner from '@/components/GameInProgressBanner.vue'
import { inverted } from '@/lib/page'

const route = useRoute()

// the minimal width of the page depends on the screen (base.css)
watchEffect(() => document.body.classList.toggle('fullscreen', route.meta.fullscreen === true))
</script>

<template>
  <!-- the game board takes the whole window -->
  <RouterView v-if="route.meta.fullscreen" />
  <div v-else class="page" :class="{ inverted }">
    <AppHeader />
    <GameInProgressBanner />
    <main class="main">
      <RouterView />
    </main>
    <AppFooter />
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--paper);
  color: var(--ink);
}

.main {
  flex: 1;
  display: flex;
  flex-direction: column;
}
</style>

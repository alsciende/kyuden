<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import GameInProgressBanner from '@/components/GameInProgressBanner.vue'
import PortraitNotice from '@/components/PortraitNotice.vue'
import QueueBanner from '@/components/queue/QueueBanner.vue'
import QueueFollower from '@/components/QueueFollower.vue'
import { inverted } from '@/lib/page'
import { waiting } from '@/lib/queueing'
import { isGuest, session } from '@/lib/session'

const route = useRoute()

/** the wait in the queue, but where it shows in full: the home of an account, the screen of the queue of a guest */
const showsQueueBanner = computed(
  () =>
    !inverted.value &&
    route.name !== 'queue' &&
    !(route.name === 'landing' && session.value !== null && !isGuest(session.value)),
)
</script>

<template>
  <!-- the game board takes the whole window -->
  <RouterView v-if="route.meta.fullscreen" />
  <div v-else class="page" :class="{ inverted }">
    <AppHeader />
    <GameInProgressBanner />
    <QueueBanner v-if="waiting !== null && showsQueueBanner" :waiting="waiting" />
    <main class="main">
      <RouterView />
    </main>
    <AppFooter />
  </div>
  <QueueFollower />
  <PortraitNotice />
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

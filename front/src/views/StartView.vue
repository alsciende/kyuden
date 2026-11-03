<script setup lang="ts">
import { computed } from 'vue'

import { session } from '@/lib/session'
import HomeView from '@/views/HomeView.vue'
import LandingView from '@/views/LandingView.vue'

/**
 * The start of the site: an account plays or watches a game, anyone else comes as a newcomer (the introductory game).
 */
const account = computed(() => session.value !== null && session.value.username !== null)
</script>

<template>
  <HomeView v-if="account" />
  <LandingView v-else />
</template>

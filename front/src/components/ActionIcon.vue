<script setup lang="ts">
import { ACTION_ICONS, type ActionIconName } from '@/lib/actionIcons'

defineProps<{ name: ActionIconName; size?: number }>()
</script>

<template>
  <svg
    :width="size ?? 22"
    :height="size ?? 22"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="path in ACTION_ICONS[name]" :key="path" :d="path" />
  </svg>
</template>

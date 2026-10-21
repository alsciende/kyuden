<script setup lang="ts">
import { nextTick, useTemplateRef } from 'vue'

import ClanMon from '@/components/ClanMon.vue'
import { CLANS, type ClanId } from '@/data/clans'

/**
 * The choice of a clan among the seven Great Clans: a group of radio buttons, browsed with the arrow keys. Disabled
 * (while a choice is being saved), it keeps the focus but ignores the choices.
 */
const props = defineProps<{
  label: string
  disabled?: boolean
}>()

const model = defineModel<ClanId | null>({ required: true })

const buttons = useTemplateRef<HTMLButtonElement[]>('buttons')

function pick(id: ClanId): void {
  if (!props.disabled) {
    model.value = id
  }
}

function browse(event: KeyboardEvent): void {
  const step = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[event.key]
  if (step === undefined || props.disabled) {
    return
  }
  event.preventDefault()
  const index = CLANS.findIndex((clan) => clan.id === model.value)
  const next = index === -1 ? (step > 0 ? 0 : CLANS.length - 1) : (index + step + CLANS.length) % CLANS.length
  model.value = CLANS[next]!.id
  void nextTick(() => buttons.value?.[next]?.focus())
}
</script>

<template>
  <div role="radiogroup" :aria-label="label" :aria-disabled="disabled" class="clans" @keydown="browse">
    <button
      v-for="(clan, index) in CLANS"
      :key="clan.id"
      ref="buttons"
      type="button"
      role="radio"
      class="clan"
      :class="{ 'is-selected': clan.id === model }"
      :style="{ '--clan-color': clan.color }"
      :aria-checked="clan.id === model"
      :tabindex="clan.id === model || (model === null && index === 0) ? 0 : -1"
      @click="pick(clan.id)"
    >
      <ClanMon :src="clan.mon" color="var(--mon-color)" :size="40" />
      <span class="clan-name">{{ clan.name }}</span>
    </button>
  </div>
</template>

<style scoped>
.clans {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.clan {
  --mon-color: var(--ink-3);
  font: inherit;
  color: inherit;
  background: transparent;
  border: 1.5px solid transparent;
  border-radius: 2px;
  padding: 12px 2px 10px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transition:
    background-color 0.2s,
    border-color 0.2s;
}

.clan:hover,
.clan.is-selected {
  --mon-color: var(--clan-color);
  background: var(--paper-2);
}

.clan.is-selected {
  border-color: var(--ink);
}

[aria-disabled='true'] .clan {
  cursor: progress;
}

.clan-name {
  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}
</style>

<script setup lang="ts">
import { computed, ref } from 'vue'

import type { GameView } from '@/api/game'
import type { Cards } from '@/lib/board'
import type { Effect } from '@/lib/playerBar'
import { groupSpecial, neededCount, specialActions } from '@/lib/specialActions'

/**
 * The special actions (`lib/specialActions.ts`) of a popover of the bars, behind a "+" with the number of those the
 * cards of the viewer need: these first, with the cards they are for, then every other one on demand. Opened at once
 * when the popover offers nothing else (`open`). A special action offered by a form of the popover (`forms`: switching
 * a ring) shows that form in the slot named after its type.
 */
const props = defineProps<{
  effects: Effect[]
  game: GameView
  cards: Cards
  sending: boolean
  /** the popover has no other action: no "+", the actions are shown at once */
  open?: boolean
  forms?: string[]
}>()

const emit = defineEmits<{ run: [effect: Effect] }>()

const groups = computed(() => groupSpecial(specialActions(props.game, props.cards), props.effects, props.forms ?? []))
const needed = computed(() => neededCount(groups.value.needed))
const expanded = ref(false)
const everyAction = ref(false)
const shown = computed(() => props.open || expanded.value)

const sections = computed(() => {
  const { needed: forCards, others } = groups.value
  const showOthers = (everyAction.value || forCards.length === 0) && others.length > 0

  return [
    ...(forCards.length ? [{ key: 'needed', label: 'For your cards', groups: forCards }] : []),
    ...(showOthers
      ? [{ key: 'others', label: forCards.length ? 'Every other action' : 'Every action', groups: others }]
      : []),
  ]
})
</script>

<template>
  <div v-if="groups.needed.length || groups.others.length" class="special-effects">
    <button
      v-if="!open"
      type="button"
      class="toggle"
      :aria-expanded="expanded"
      :aria-label="needed ? `More actions, ${needed} for your cards` : 'More actions'"
      @click="expanded = !expanded"
    >
      <svg
        aria-hidden="true"
        width="14"
        height="14"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
      >
        <path d="M5 12h14" />
        <path v-if="!expanded" d="M12 5v14" />
      </svg>
      More actions
      <span v-if="needed && !expanded" class="badge" aria-hidden="true">{{ needed }}</span>
    </button>

    <template v-if="shown">
      <div v-for="section in sections" :key="section.key" class="section">
        <span class="label">{{ section.label }}</span>
        <span v-if="section.key === 'others'" class="hint">
          No card you know needs them: for an effect the engine does not know about.
        </span>
        <div v-for="group in section.groups" :key="group.type" class="group">
          <div v-if="group.items.length" class="effects">
            <button
              v-for="effect in group.items"
              :key="effect.label"
              type="button"
              class="effect"
              :disabled="sending"
              @click="emit('run', effect)"
            >
              {{ effect.label }}
            </button>
          </div>
          <slot v-if="group.form" :name="group.type" />
          <span v-if="group.sources" class="source">{{ group.sources }}</span>
        </div>
      </div>
      <button
        v-if="groups.needed.length && groups.others.length"
        type="button"
        class="btn-link every"
        @click="everyAction = !everyAction"
      >
        {{ everyAction ? 'Only the actions for your cards' : 'Show every action' }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.special-effects {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.toggle {
  position: relative;
  align-self: flex-end;
  font: inherit;
  font-weight: 700;
  font-size: 13px;
  min-height: 32px;
  padding: 0 10px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.toggle[aria-expanded='true'] {
  background: var(--ink);
  color: var(--card);
}

.badge {
  position: absolute;
  top: -8px;
  right: -8px;
  min-width: 18px;
  height: 18px;
  box-sizing: border-box;
  padding: 0 4px;
  border-radius: 9px;
  background: var(--seal);
  color: var(--card);
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section + .section {
  padding-top: 10px;
  border-top: 1px solid var(--line);
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.effects {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.effect {
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  min-height: 40px;
  padding: 0 10px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.effect:hover {
  background: var(--paper-2);
}

.effect:disabled {
  opacity: 0.5;
  cursor: default;
}

.hint,
.source {
  font-size: 12px;
  color: var(--ink-2);
}

.every {
  align-self: flex-start;
  font-size: 13px;
}
</style>

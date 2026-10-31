<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import type { Element, GameView, PayloadArgs, Send } from '@/api/game'
import type { MessageType } from '@/api/messages'
import { rings } from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES, RING_EFFECTS, ringTip } from '@/lib/icons'
import { ringEffects, ringsToSwitchWith } from '@/lib/rings'
import { vTip } from '@/lib/tooltip'

/**
 * The window of a ring, opened by a click on it in the bar on the right (under the rings) or in the bar of a player
 * (`anchor`: above or below the bar, under the ring clicked): where it is (unclaimed with its fate, contested, or
 * claimed by whom and in which type of conflict, which card effects refer to), its effect, and the effects on it:
 * switch it with another ring, claim it, take the fate on it or place fate on it.
 */
const props = defineProps<{
  game: GameView
  element: Element
  sending: boolean
  error: string | null
  send: Send
  /** in the bar of a player: its left in the bar, its width, and the side of the board the bar is on */
  anchor?: { left: number; width: number; position: 'top' | 'bottom' }
}>()

const emit = defineEmits<{ close: [] }>()

const ring = computed(() => rings(props.game).find((candidate) => candidate.element === props.element))
const amount = ref(1)

const where = computed(() => {
  switch (ring.value?.status) {
    case 'contested':
      return 'Contested'
    case 'yours':
      return 'Claimed by you'
    case 'theirs':
      return 'Claimed by Opp.'
    default:
      return 'Unclaimed'
  }
})

/** the type of the conflict it was claimed in, or of the conflict it is contested in */
const conflictType = computed(() =>
  ring.value?.status === 'contested' ? (props.game.conflict?.type ?? null) : (ring.value?.conflictType ?? null),
)

const switchChoices = computed(() =>
  props.game.available_actions.includes('switch_ring') ? ringsToSwitchWith(props.game, props.element) : [],
)
const effects = computed(() => ringEffects(props.game, props.element, amount.value))

async function run<T extends MessageType>(type: T, ...payload: PayloadArgs<T>): Promise<void> {
  if (await props.send(type, ...payload)) {
    emit('close')
  }
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="backdrop" @click="emit('close')" />
  <div
    class="popover"
    :class="anchor?.position"
    :style="anchor && { left: `${anchor.left}px`, right: 'auto', width: `${anchor.width}px` }"
    role="dialog"
    :aria-label="`${ELEMENT_NAMES[element]} ring`"
  >
    <div class="head">
      <span class="label">{{ ELEMENT_NAMES[element] }} ring · {{ where }}</span>
      <button type="button" class="close" aria-label="Close" @click="emit('close')">
        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
          <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
        </svg>
      </button>
    </div>

    <p v-if="conflictType" class="claimed">
      <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[conflictType] }}</span>
      {{ ring?.status === 'contested' ? 'In a' : 'Claimed in a' }} {{ conflictType }} conflict
    </p>
    <p v-if="ring && ring.fate > 0" class="claimed">{{ ring.fate }} fate on it</p>
    <p class="hint">{{ RING_EFFECTS[element] }}</p>

    <template v-if="switchChoices.length">
      <span class="label">Switch with</span>
      <div class="switch-rings">
        <button
          v-for="choice in switchChoices"
          :key="choice.element"
          v-tip="ringTip(choice.element)"
          type="button"
          class="switch-ring"
          :class="choice.status"
          :disabled="sending"
          :aria-label="`Switch with the ${ELEMENT_NAMES[choice.element]} ring`"
          @click="run('switch_ring', { ring: element, other_ring: choice.element })"
        >
          <span class="icon">{{ ELEMENT_ICONS[choice.element] }}</span>
        </button>
      </div>
    </template>

    <template v-if="effects.length">
      <div class="amount">
        <span>Fate</span>
        <button type="button" class="step" aria-label="Less" :disabled="amount <= 1" @click="amount--">−</button>
        <b aria-live="polite">{{ amount }}</b>
        <button type="button" class="step" aria-label="More" @click="amount++">+</button>
      </div>
      <div class="effects">
        <button
          v-for="(effect, index) in effects"
          :key="effect.label"
          type="button"
          class="effect"
          :class="{ primary: index === 0 }"
          :disabled="sending"
          @click="run(effect.type, effect.payload)"
        >
          {{ effect.label }}
        </button>
      </div>
    </template>
    <p v-else-if="!switchChoices.length" class="hint">Nothing to do now.</p>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  z-index: 30;
}

.popover {
  position: absolute;
  z-index: 31;
  left: 0;
  right: 0;
  top: calc(100% + 8px);
  box-sizing: border-box;
  background: var(--card);
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  box-shadow: 0 14px 34px rgb(31 27 22 / 0.28);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* in the bar of the viewer, below the board: above the bar */
.popover.bottom {
  top: auto;
  bottom: calc(100% + 8px);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.close {
  font: inherit;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 2px;
  background: transparent;
  color: var(--ink-2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.close:hover {
  background: var(--paper-2);
}

.claimed {
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}

.claimed .icon {
  font-size: 18px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.switch-rings {
  display: flex;
  gap: 8px;
}

.switch-ring {
  font: inherit;
  width: 40px;
  height: 40px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 1.5px solid var(--ink);
  background: transparent;
  color: var(--ink);
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.switch-ring.contested {
  border: 2px solid var(--seal);
  color: var(--seal);
}

.switch-ring.yours {
  background: var(--ink);
  color: var(--paper);
}

.switch-ring.theirs {
  background: var(--paper-3);
  color: var(--ink-2);
  border: 1.5px dashed var(--ink-3);
}

.switch-ring:hover {
  outline: 2px solid var(--seal);
  outline-offset: 2px;
}

.amount {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}

.amount > span {
  flex: 1;
}

.amount b {
  font-family: var(--font-display);
  font-size: 22px;
  min-width: 24px;
  text-align: center;
}

.step {
  font: inherit;
  font-weight: 700;
  font-size: 18px;
  width: 40px;
  height: 40px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
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

.effect.primary {
  background: var(--ink);
  color: var(--paper);
}

.step:disabled,
.effect:disabled,
.switch-ring:disabled {
  opacity: 0.5;
  cursor: default;
}

.hint {
  font-size: 12px;
  color: var(--ink-2);
}

.error {
  font-size: 13px;
  color: var(--seal);
}
</style>

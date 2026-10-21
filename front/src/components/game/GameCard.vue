<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'

import type { CardView } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { printed, shownSkill, type Cards } from '@/lib/board'
import { CONFLICT_ICONS } from '@/lib/icons'
import { hidePreview, showPreview } from '@/lib/preview'

/**
 * A card on the board: its face (name, type, values) when the viewer sees it, its back otherwise. A bowed card lies
 * sideways; fate and personal honor are marked on it. Hovering it shows its image beside it. A character shows its
 * current skills: recorded by a player (solid), else computed from the cards (dotted underline, nobody recorded them).
 */
const props = withDefaults(
  defineProps<{
    card: CardView
    cards: Cards
    /** the card belongs to the viewer: its image is shown above it */
    mine: boolean
    size?: 'small' | 'normal'
    selected?: boolean
    muted?: boolean
    /** a word on the card, e.g. what it does in the conflict */
    tag?: string | null
    /** the cost of a character instead of its glory: in a province, to be played */
    showCost?: boolean
    /** the attachments of a character in play, which change its skills */
    attachments?: CardView[]
  }>(),
  { size: 'normal', selected: false, muted: false, tag: null, showCost: false, attachments: () => [] },
)

defineEmits<{ click: [] }>()

const element = useTemplateRef<HTMLButtonElement>('element')

const printedCard = computed(() => (props.card.code === null ? undefined : props.cards[props.card.code]))
const hidden = computed(() => props.card.code === null)
const bowed = computed(() => props.card.orientation === 'sideways')
const broken = computed(() => props.card.orientation === 'upside_down')
/** a card the viewer knows although it is face down (their own provinces) */
const secret = computed(() => !hidden.value && props.card.orientation === 'face_down')

const isCharacter = computed(() => printedCard.value?.type === 'character')

const skills = computed(() => {
  const card = printedCard.value
  if (!isCharacter.value || card === undefined) {
    return null
  }
  const character = { card: props.card, attachments: props.attachments }

  return {
    military: shownSkill(character, props.cards, 'military'),
    political: shownSkill(character, props.cards, 'political'),
    glory: printed(card.glory),
  }
})

function skillLabel(type: string, skill: { value: number | null; recorded: boolean }): string {
  return skill.value === null
    ? `no ${type} skill`
    : `${type} ${skill.value} (${skill.recorded ? 'recorded' : 'computed'})`
}

const corner = computed(() => {
  const card = printedCard.value
  if (card === undefined) {
    return null
  }
  if (card.type === 'province') {
    return `${printed(card.strength)}`
  }
  if (card.type === 'stronghold') {
    return null
  }
  if (card.cost !== null && card.cost !== undefined) {
    return `Cost ${card.cost}`
  }

  return null
})

const label = computed(() => {
  if (hidden.value) {
    return 'Face-down card'
  }
  const parts = [printedCard.value?.name ?? 'Card']
  if (skills.value) {
    parts.push(skillLabel('military', skills.value.military), skillLabel('political', skills.value.political))
  }
  if (bowed.value) {
    parts.push('bowed')
  }
  if (secret.value) {
    parts.push('face down')
  }
  if (broken.value) {
    parts.push('broken')
  }
  if (props.card.fate > 0) {
    parts.push(`${props.card.fate} fate`)
  }
  if (props.card.personal_honor !== 'ordinary') {
    parts.push(props.card.personal_honor)
  }

  return parts.join(', ')
})

function enter(): void {
  if (element.value) {
    showPreview(props.card.code, element.value, props.mine)
  }
}
</script>

<template>
  <button
    ref="element"
    type="button"
    class="card"
    :class="[size, { hidden, bowed, broken, secret, selected, muted }]"
    :aria-label="label"
    :aria-pressed="selected ? 'true' : undefined"
    @click="$emit('click')"
    @mouseenter="enter"
    @mouseleave="hidePreview"
    @focus="enter"
    @blur="hidePreview"
  >
    <template v-if="hidden">
      <ClanMon :src="enso" :size="size === 'small' ? 30 : 44" color="#a89c88" />
    </template>
    <template v-else>
      <span class="name">{{ printedCard?.name ?? '…' }}</span>
      <span class="kind">{{ secret ? 'Face down' : broken ? 'Broken' : (printedCard?.type ?? '') }}</span>
      <span v-if="skills" class="stats">
        <span
          class="skill"
          :class="{ recorded: skills.military.recorded, computed: !skills.military.recorded }"
          :title="skills.military.recorded ? 'Recorded by a player' : 'Computed from the cards, not recorded'"
          ><span class="icon" aria-hidden="true">{{ CONFLICT_ICONS.military }}</span
          ><span class="value">{{ skills.military.value ?? '–' }}</span></span
        >
        <span v-if="showCost && printedCard?.cost !== null" class="cost">{{ printedCard?.cost }}</span>
        <span v-else class="glory">{{ skills.glory }}</span>
        <span
          class="skill"
          :class="{ recorded: skills.political.recorded, computed: !skills.political.recorded }"
          :title="skills.political.recorded ? 'Recorded by a player' : 'Computed from the cards, not recorded'"
          ><span class="icon" aria-hidden="true">{{ CONFLICT_ICONS.political }}</span
          ><span class="value">{{ skills.political.value ?? '–' }}</span></span
        >
      </span>
      <span v-else-if="corner" class="stats"
        ><span /><span class="corner">{{ corner }}</span></span
      >
    </template>
    <span v-if="card.fate > 0" class="fate" aria-hidden="true">{{ card.fate }}</span>
    <span v-if="card.personal_honor !== 'ordinary'" class="honor" :class="card.personal_honor" aria-hidden="true">
      {{ card.personal_honor === 'honored' ? 'Honored' : 'Dishonored' }}
    </span>
    <span v-if="tag" class="tag" aria-hidden="true">{{ tag }}</span>
  </button>
</template>

<style scoped>
.card {
  --w: 84px;
  --h: 118px;
  font: inherit;
  color: var(--ink);
  text-align: left;
  width: var(--w);
  height: var(--h);
  flex: none;
  position: relative;
  box-sizing: border-box;
  padding: 8px;
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  background: var(--card);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 4px;
  cursor: pointer;
  transition:
    outline-color 0.15s,
    transform 0.15s;
}

.card:hover {
  transform: translateY(-2px);
}

.small {
  --w: 58px;
  --h: 81px;
  padding: 5px;
}

.small .kind {
  display: none;
}

.bowed {
  width: var(--h);
  height: var(--w);
  background: var(--paper-2);
  border-color: var(--ink-3);
}

.hidden {
  background: var(--ink);
  border-color: var(--ink);
  align-items: center;
  justify-content: center;
}

.secret {
  border-style: dashed;
  background: var(--paper-2);
}

.broken {
  background: var(--paper-3);
  border-color: var(--ink-3);
}

.broken .name {
  text-decoration: line-through;
}

.muted {
  opacity: 0.4;
}

.selected {
  outline: 3px solid var(--seal);
  outline-offset: 3px;
}

.name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 13px;
  line-height: 1.1;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
}

.small .name {
  font-size: 10px;
}

.kind {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-3);
  margin-top: auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stats {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 12px;
  font-weight: 700;
}

.small .stats {
  font-size: 10px;
}

.stats .icon {
  font-size: 0.9em;
  margin-right: 2px;
}

.skill.computed .value {
  text-decoration: underline dotted var(--ink-3);
  text-underline-offset: 2px;
}

.skill.recorded {
  color: var(--seal);
}

.glory,
.corner {
  color: var(--ink-3);
}

.cost {
  color: var(--seal);
}

.fate {
  position: absolute;
  top: -9px;
  right: -9px;
  min-width: 22px;
  height: 22px;
  box-sizing: border-box;
  padding: 0 5px;
  border-radius: 11px;
  background: var(--ink);
  color: var(--seal-ink);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--board);
}

.honor {
  position: absolute;
  top: -9px;
  left: 6px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 2px 5px;
  border-radius: 2px;
}

.honor.honored {
  background: var(--ink);
  color: var(--paper);
}

.honor.dishonored {
  background: var(--seal);
  color: var(--seal-ink);
}

.tag {
  position: absolute;
  bottom: -11px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--seal);
  color: var(--seal-ink);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 3px 7px;
  border-radius: 2px;
  white-space: nowrap;
}
</style>

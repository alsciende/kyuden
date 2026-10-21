<script setup lang="ts">
import { computed } from 'vue'

import type { GameView, PlayerView } from '@/api/game'
import GameCard from '@/components/game/GameCard.vue'
import { printed, provinces, type Cards } from '@/lib/board'
import { hidePreview, showPreview } from '@/lib/preview'

/**
 * The provinces of a player, each with what lies on it (dynasty cards, the stronghold) above its name. In a
 * selection, the provinces that can be chosen are the targets of the click.
 */
const props = withDefaults(
  defineProps<{
    game: GameView
    player: PlayerView
    cards: Cards
    size?: 'small' | 'normal'
    /** the provinces that can be chosen, by card id; null: no selection */
    selectable?: string[] | null
    selectedId?: string | null
  }>(),
  { size: 'normal', selectable: null, selectedId: null },
)

const emit = defineEmits<{ open: [id: string]; select: [id: string] }>()

const mine = computed(() => props.player.id === props.game.viewer_id)

const list = computed(() =>
  provinces(props.player).map((province) => {
    const card = province.province
    const printedCard = card?.code ? props.cards[card.code] : undefined
    const top = province.stronghold ?? province.cards.at(-1) ?? null

    return {
      province,
      top,
      card,
      name: printedCard?.name ?? (card?.code === null ? 'Face-down province' : 'Province'),
      strength: printedCard ? printed(printedCard.strength) : null,
      hidden: card?.code === null,
      stronghold: province.stronghold !== null,
      selectable: card !== null && props.selectable !== null && props.selectable.includes(card.id),
    }
  }),
)

function clickProvince(id: string, selectable: boolean): void {
  if (selectable) {
    emit('select', id)
  } else {
    emit('open', id)
  }
}
</script>

<template>
  <div class="provinces" :class="size">
    <div v-for="item in list" :key="item.province.stack.id" class="province">
      <div class="slot">
        <GameCard
          v-if="item.top"
          :card="item.top"
          :cards="cards"
          :mine="mine"
          :size="size"
          :muted="selectable !== null"
          show-cost
          @click="emit('open', item.top.id)"
        />
        <span v-else class="empty" aria-hidden="true" />
      </div>
      <button
        v-if="item.card"
        type="button"
        class="tag"
        :class="{
          hidden: item.hidden,
          broken: item.province.broken,
          selectable: item.selectable,
          selected: item.card.id === selectedId,
          muted: selectable !== null && !item.selectable,
        }"
        :aria-pressed="item.selectable ? item.card.id === selectedId : undefined"
        @click="clickProvince(item.card.id, item.selectable)"
        @mouseenter="showPreview(item.card.code, $event.currentTarget as HTMLElement, mine)"
        @mouseleave="hidePreview"
      >
        <span class="tag-name">{{ item.name }}</span>
        <span v-if="item.province.broken" class="tag-value">Broken</span>
        <span v-else-if="item.strength !== null" class="tag-value">{{ item.strength }}</span>
        <span v-if="item.stronghold" class="visually-hidden">(stronghold province)</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.provinces {
  display: flex;
  gap: 18px;
  align-items: flex-start;
}

.small {
  gap: 12px;
}

.province {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 118px;
  align-items: flex-start;
}

.small .province {
  width: 81px;
}

.slot {
  height: 118px;
  display: flex;
  align-items: flex-end;
}

.small .slot {
  height: 81px;
}

.empty {
  width: 84px;
  height: 118px;
  box-sizing: border-box;
  border: 1.5px dashed var(--line);
  border-radius: 4px;
}

.small .empty {
  width: 58px;
  height: 81px;
}

.tag {
  font: inherit;
  color: var(--ink);
  width: 100%;
  min-height: 32px;
  box-sizing: border-box;
  padding: 5px 6px;
  border: 0;
  border-top: 1.5px solid var(--ink);
  background: transparent;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 6px;
  font-size: 11px;
  text-align: left;
  cursor: pointer;
}

.tag:hover {
  background: var(--paper-2);
}

.tag-name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 13px;
  line-height: 1.1;
}

.small .tag-name {
  font-size: 11px;
}

.tag-value {
  font-weight: 700;
}

.hidden .tag-name {
  color: var(--ink-3);
  font-style: italic;
  font-weight: 500;
}

.broken {
  border-top-style: dashed;
  color: var(--ink-3);
}

.broken .tag-name {
  text-decoration: line-through;
}

.selectable {
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
  min-height: 48px;
}

.selected {
  outline: 3px solid var(--seal);
  outline-offset: 3px;
}

.muted {
  opacity: 0.45;
}
</style>

<script setup lang="ts">
import type { Cards, Character } from '@/lib/board'
import GameCard from '@/components/game/GameCard.vue'

/**
 * Characters with their attachments, tucked under them, after the cards of the default slot (e.g. the contested
 * province). In a selection, the characters that can be chosen are the
 * targets of the click; the others are dimmed.
 */
withDefaults(
  defineProps<{
    characters: Character[]
    cards: Cards
    mine: boolean
    size?: 'small' | 'normal'
    selectable?: string[] | null
    selectedIds?: string[]
    /** a word on the selected characters */
    selectedTag?: string | null
    /** a word by character id */
    tags?: Record<string, string>
  }>(),
  { size: 'normal', selectable: null, selectedIds: () => [], selectedTag: null, tags: () => ({}) },
)

const emit = defineEmits<{ open: [id: string]; select: [id: string] }>()

function click(id: string, selectable: string[] | null): void {
  if (selectable?.includes(id)) {
    emit('select', id)
  } else {
    emit('open', id)
  }
}
</script>

<template>
  <div class="characters" :class="size">
    <slot />
    <div v-for="character in characters" :key="character.stack.id" class="unit">
      <GameCard
        :card="character.card"
        :cards="cards"
        :mine="mine"
        :size="size"
        :attachments="character.attachments"
        :selected="selectedIds.includes(character.card.id)"
        :muted="selectable !== null && !selectable.includes(character.card.id)"
        :tag="selectedIds.includes(character.card.id) ? selectedTag : (tags[character.card.id] ?? null)"
        @click="click(character.card.id, selectable)"
      />
      <GameCard
        v-for="attachment in character.attachments"
        :key="attachment.id"
        class="attachment"
        :card="attachment"
        :cards="cards"
        :mine="mine"
        size="small"
        @click="emit('open', attachment.id)"
      />
    </div>
    <p v-if="characters.length === 0" class="none">No character</p>
  </div>
</template>

<style scoped>
.characters {
  display: flex;
  flex-wrap: wrap;
  gap: 18px 22px;
  align-items: flex-end;
  min-height: 118px;
}

.characters:has(.none) {
  min-height: 0;
}

.small {
  min-height: 81px;
  gap: 12px 14px;
}

.unit {
  display: flex;
  align-items: flex-end;
}

.attachment {
  margin-left: -18px;
  margin-bottom: -10px;
}

.none {
  align-self: center;
  font-size: 13px;
  color: var(--ink-3);
}
</style>

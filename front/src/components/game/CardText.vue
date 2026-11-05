<script setup lang="ts">
import ClanMon from '@/components/ClanMon.vue'
import type { TextSegment } from '@/lib/cardText'

/**
 * The text of a card as printed (lib/cardText), paragraph by paragraph; a dimmed paragraph is not the one that matters
 * now.
 */
defineProps<{
  paragraphs: { segments: TextSegment[]; emphasized: boolean }[]
}>()
</script>

<template>
  <div class="card-text">
    <p v-for="(paragraph, index) in paragraphs" :key="index" :class="{ dim: !paragraph.emphasized }">
      <template v-for="(segment, position) in paragraph.segments" :key="position">
        <span v-if="segment.kind === 'icon'" class="icon" role="img" :aria-label="segment.label">{{
          segment.glyph
        }}</span>
        <ClanMon
          v-else-if="segment.kind === 'clan'"
          :src="segment.mon"
          :size="13"
          color="currentColor"
          :label="segment.label"
        />
        <b v-else-if="segment.bold" :class="{ timing: segment.timing && paragraph.emphasized }">{{ segment.text }}</b>
        <i v-else-if="segment.italic">{{ segment.text }}</i>
        <template v-else>{{ segment.text }}</template>
      </template>
    </p>
  </div>
</template>

<style scoped>
.card-text {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

p {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  color: var(--ink);
}

p.dim {
  color: var(--ink-3);
}

b {
  font-weight: 700;
}

b.timing {
  color: var(--seal);
}

.mon {
  vertical-align: -1px;
}
</style>

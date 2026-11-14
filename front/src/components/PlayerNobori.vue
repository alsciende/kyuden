<script setup lang="ts">
import { computed } from 'vue'

import ClanMon from '@/components/ClanMon.vue'
import { monOf, noboriLabel, noboriSizes, patternOf, type Banner } from '@/data/nobori'

/**
 * The nobori of a player (`data/nobori.ts`): paper, its pattern repeated in the ink of the patterns (the tile used as
 * a mask), and at its top the mon in its own color on a disc of paper edged with a hairline. It takes the height its
 * parent gives it; its width sets the size of the mon.
 */
const props = withDefaults(
  defineProps<{
    banner: Banner
    width?: number
    decorative?: boolean
    /** the pattern alone, without the mon: a swatch */
    bare?: boolean
  }>(),
  { width: 56, decorative: false, bare: false },
)

const mon = computed(() => monOf(props.banner.mon))
const pattern = computed(() => patternOf(props.banner.pattern))
const sizes = computed(() => noboriSizes(props.width))

const patternStyle = computed(() => {
  const tile = pattern.value.tile
  if (tile === null) {
    return {}
  }
  const size = `${tile.width * sizes.value.scale}px ${tile.height * sizes.value.scale}px`

  return {
    maskImage: `url("${tile.src}")`,
    WebkitMaskImage: `url("${tile.src}")`,
    maskSize: size,
    WebkitMaskSize: size,
  }
})
</script>

<template>
  <div
    class="nobori"
    :style="{ width: `${width}px`, paddingTop: `${sizes.top}px` }"
    :role="decorative ? undefined : 'img'"
    :aria-label="decorative ? undefined : noboriLabel(banner)"
    :aria-hidden="decorative ? 'true' : undefined"
  >
    <span
      v-if="pattern.id !== 'none'"
      class="pattern"
      :class="{ tiled: pattern.tile !== null }"
      :style="patternStyle"
    />
    <span v-if="!bare" class="disc" :style="{ width: `${sizes.disc}px`, height: `${sizes.disc}px` }">
      <span
        v-if="mon.ringed"
        class="ring"
        :style="{
          width: `${sizes.mon}px`,
          height: `${sizes.mon}px`,
          borderWidth: `${sizes.ring}px`,
          borderColor: mon.color,
        }"
      >
        <ClanMon :src="mon.src" :color="mon.color" :size="sizes.ringed" />
      </span>
      <ClanMon v-else :src="mon.src" :color="mon.color" :size="sizes.mon" />
    </span>
  </div>
</template>

<style scoped>
.nobori {
  position: relative;
  flex: none;
  box-sizing: border-box;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  overflow: hidden;
  background: var(--nobori-paper);
}

.pattern {
  position: absolute;
  inset: 0;
  background: var(--nobori-ink);
}

.pattern.tiled {
  mask-repeat: repeat;
  -webkit-mask-repeat: repeat;
  mask-position: 0 0;
  -webkit-mask-position: 0 0;
}

.disc {
  position: relative;
  flex: none;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--nobori-line);
  border-radius: 50%;
  background: var(--nobori-paper);
}

.ring {
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border-style: solid;
  border-radius: 50%;
}
</style>

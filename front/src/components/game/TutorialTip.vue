<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'

import { placeTip, type Tip, type TipPlacement } from '@/lib/tutorial'

/**
 * A tip of the tutorial, beside the part of the screen it explains (its anchor: `data-tip-anchor`), marked by a ring
 * while it shows. Over a window (a card opened), it is drawn inside the window, so that the page under it, made inert,
 * does not take it along. It follows its anchor while it moves, and waits for it while it is not on the screen.
 */
const props = defineProps<{
  tip: Tip
  /** a card is opened over the page: the tip goes in its window */
  overWindow: boolean
}>()
const emit = defineEmits<{ dismiss: []; hide: [] }>()

const element = useTemplateRef<HTMLElement>('element')
const placement = ref<TipPlacement | null>(null)

/**
 * The window of the card opened (mounted again when it opens or closes): resolved once the page is mounted (`defer`),
 * the window being drawn with the tip.
 */
const target = props.overWindow ? '[data-card-dialog]' : 'body'

let anchor: Element | null = null
let timer: ReturnType<typeof setInterval> | null = null

function mark(next: Element | null): void {
  if (next === anchor) {
    return
  }
  anchor?.removeAttribute('data-tip-active')
  next?.setAttribute('data-tip-active', '')
  anchor = next
}

function place(): void {
  const found = document.querySelector(`[data-tip-anchor="${props.tip.anchor}"]`)
  mark(found)
  const tip = element.value
  if (found === null || tip === null) {
    placement.value = null

    return
  }
  const rect = found.getBoundingClientRect()
  if (rect.width === 0 && rect.height === 0) {
    placement.value = null

    return
  }
  placement.value = placeTip(
    rect,
    { width: tip.offsetWidth, height: tip.offsetHeight },
    { width: window.innerWidth, height: window.innerHeight },
    // the image of a card opened is on the left of its actions
    props.tip.anchor === 'card-actions' ? 'right' : 'left',
  )
}

onMounted(() => {
  place()
  // the board changes under the tip without telling it: a few reads of a rectangle a second
  timer = setInterval(place, 250)
  window.addEventListener('resize', place)
})

onBeforeUnmount(() => {
  if (timer !== null) {
    clearInterval(timer)
  }
  window.removeEventListener('resize', place)
  mark(null)
})
</script>

<template>
  <Teleport :to="target" defer>
    <aside
      ref="element"
      class="tutorial-tip"
      :class="placement?.side ?? 'hidden'"
      :style="
        placement ? { left: `${placement.left}px`, top: `${placement.top}px`, '--arrow': `${placement.arrow}px` } : {}
      "
      role="note"
      :aria-label="`Tip: ${tip.title}`"
    >
      <span v-if="placement && placement.side !== 'inside'" class="arrow" aria-hidden="true"></span>
      <span class="label"
        >Tip<template v-if="tip.sequence"> · {{ tip.sequence }}</template></span
      >
      <h2 class="title">{{ tip.title }}</h2>
      <p v-for="(paragraph, index) in tip.paragraphs" :key="index" class="text">
        <template v-for="(segment, at) in paragraph" :key="at">
          <strong v-if="segment.strong">{{ segment.text }}</strong>
          <template v-else>{{ segment.text }}</template>
        </template>
      </p>
      <div class="buttons">
        <button type="button" class="hide" @click="emit('hide')">Hide tips</button>
        <button type="button" class="got-it" @click="emit('dismiss')">Got it</button>
      </div>
    </aside>
  </Teleport>
</template>

<style scoped>
.tutorial-tip {
  position: fixed;
  z-index: 30;
  width: 320px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 18px;
  border: 2px solid var(--tutorial);
  border-radius: 10px;
  background: var(--card);
  color: var(--ink);
  box-shadow: 0 10px 30px rgb(20 40 90 / 18%);
  font-family: var(--font-ui);
  text-align: left;
}

.hidden {
  visibility: hidden;
  left: 0;
  top: 0;
}

.arrow {
  position: absolute;
  width: 18px;
  height: 18px;
  background: var(--card);
  border: 0 solid var(--tutorial);
  transform: rotate(45deg);
}

/* the tip is on the left of its anchor: its arrow on its right edge, pointing right */
.left .arrow {
  right: -11px;
  top: calc(var(--arrow) - 9px);
  border-top-width: 2px;
  border-right-width: 2px;
}

.right .arrow {
  left: -11px;
  top: calc(var(--arrow) - 9px);
  border-bottom-width: 2px;
  border-left-width: 2px;
}

.below .arrow {
  top: -11px;
  left: calc(var(--arrow) - 9px);
  border-top-width: 2px;
  border-left-width: 2px;
}

.above .arrow {
  bottom: -11px;
  left: calc(var(--arrow) - 9px);
  border-bottom-width: 2px;
  border-right-width: 2px;
}

.label {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--tutorial);
}

.title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 600;
  line-height: 1.15;
}

.text {
  margin: 0;
  font-size: 15px;
  line-height: 1.45;
}

.buttons {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.hide {
  padding: 8px 0;
  border: 0;
  background: transparent;
  color: var(--ink-2);
  font: inherit;
  font-size: 14px;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.got-it {
  min-height: 44px;
  padding: 10px 18px;
  border: 0;
  border-radius: 6px;
  background: var(--tutorial);
  color: var(--tutorial-ink);
  font: inherit;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}
</style>

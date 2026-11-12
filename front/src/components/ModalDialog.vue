<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue'

import { modalClosed, modalOpened } from '@/lib/topLayer'

/**
 * A window over the page, open as long as it is mounted: a native modal `<dialog>`, so that the browser draws it
 * above everything else (the top layer), keeps the focus in it, makes the page under it inert and gives the focus
 * back to what opened it. It fills the window of the browser: its parent styles it as the veil (its class falls
 * through), with the content in its slot.
 *
 * Escape, or the back gesture of the platform, asks to close it (`close`) when it is `dismissable`; else nothing
 * closes it but its parent.
 */
const props = defineProps<{ dismissable?: boolean }>()
const emit = defineEmits<{ close: [] }>()

const dialog = useTemplateRef<HTMLDialogElement>('dialog')
let leaving = false

function show(): void {
  const element = dialog.value
  if (!element || element.open) {
    return
  }
  if (typeof element.showModal === 'function') {
    element.showModal()
  } else {
    // a browser without modal dialogs: shown where its styles put it
    element.setAttribute('open', '')
  }
}

/** the parent decides: the dialog stays open until it is unmounted */
function onCancel(event: Event): void {
  event.preventDefault()
  if (props.dismissable) {
    emit('close')
  }
}

/** closed by the browser all the same (a close request repeated without touching the page) */
function onClose(): void {
  if (leaving) {
    return
  }
  if (props.dismissable) {
    emit('close')
  } else {
    show()
  }
}

onMounted(() => {
  modalOpened()
  show()
})

onBeforeUnmount(() => {
  leaving = true
  modalClosed()
  // closing it gives the focus back to what opened it
  const element = dialog.value
  if (element?.open && typeof element.close === 'function') {
    element.close()
  }
})
</script>

<template>
  <dialog ref="dialog" class="modal" @cancel="onCancel" @close="onClose">
    <slot />
  </dialog>
</template>

<style scoped>
/* no specificity: the styles of the parent win; `display` is theirs too, the dialog being open while mounted */
:where(.modal) {
  position: fixed;
  inset: 0;
  width: auto;
  height: auto;
  max-width: none;
  max-height: none;
  margin: 0;
  border: 0;
  padding: 0;
  background: none;
  color: var(--ink);
  /* a scroll in the window never scrolls the page under it */
  overscroll-behavior: contain;
}

/* the dialog is its own veil */
:where(.modal)::backdrop {
  background: none;
  overflow: hidden;
  overscroll-behavior: contain;
}
</style>

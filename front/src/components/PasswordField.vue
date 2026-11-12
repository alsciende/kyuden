<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'

/**
 * The field of a password, with a button to read what was typed: hidden again once its form is sent. What the field
 * is given (its id, `autocomplete`, `required`, what describes it…) goes to the input.
 */
defineOptions({ inheritAttrs: false })

const password = defineModel<string>({ required: true })

const input = useTemplateRef<HTMLInputElement>('input')
const shown = ref(false)
let form: HTMLFormElement | null = null

function hide(): void {
  shown.value = false
}

onMounted(() => {
  form = input.value?.form ?? null
  form?.addEventListener('submit', hide)
})

onBeforeUnmount(() => form?.removeEventListener('submit', hide))
</script>

<template>
  <div class="password">
    <input ref="input" v-model="password" v-bind="$attrs" class="field field-ui" :type="shown ? 'text' : 'password'" />
    <!-- its name says what it does now: no pressed state beside it -->
    <button type="button" class="toggle" :aria-controls="input?.id || undefined" @click="shown = !shown">
      {{ shown ? 'Hide' : 'Show' }}<span class="visually-hidden"> password</span>
    </button>
  </div>
</template>

<style scoped>
.password {
  position: relative;
}

.field {
  padding-right: 84px;
}

.toggle {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  min-width: 72px;
  padding: 0 14px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ink-2);
  background: none;
  border: 0;
  cursor: pointer;
}

@media (hover: hover) {
  .toggle:hover {
    color: var(--seal);
  }
}

/* inside the border of the field */
.toggle:focus-visible {
  outline-offset: -5px;
}
</style>

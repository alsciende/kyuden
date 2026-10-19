<script setup lang="ts">
/**
 * Where the newcomer stands on the way to their first game.
 */
const props = defineProps<{ current: number }>()

const steps = ['Clan', 'Name', 'Opponent']

function state(index: number): string {
  if (index === props.current) {
    return 'is-current'
  }

  return index < props.current ? 'is-done' : ''
}
</script>

<template>
  <nav aria-label="Progress" class="trail">
    <ol>
      <li
        v-for="(label, index) in steps"
        :key="label"
        :class="state(index)"
        :aria-current="index === current ? 'step' : undefined"
      >
        <span class="dot">{{ index + 1 }}</span
        >{{ label }}
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.trail ol {
  list-style: none;
  margin: 0;
  padding: 22px 48px 0;
  display: flex;
  justify-content: center;
  gap: 40px;
}

li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink-3);
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1.5px solid var(--line);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.is-current {
  color: var(--ink);
}

.is-current .dot {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.is-done {
  color: var(--ink-2);
}

.is-done .dot {
  border-color: var(--ink-2);
}
</style>

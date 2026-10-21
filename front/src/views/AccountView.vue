<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { MAX_NAME_LENGTH } from '@/lib/names'
import { formErrors } from '@/lib/credentials'
import { refreshSession, rename, session, signOut } from '@/lib/session'

/**
 * The account of the player: their username (how they sign in) and their name at court (what the other players see),
 * which they change here.
 */
const route = useRoute()
const router = useRouter()

const created = computed(() => route.query.created === '1')
const name = ref(session.value?.name ?? '')
const saved = ref(false)
const error = ref<string | null>(null)
const sending = ref(false)

const changed = computed(() => name.value.trim() !== '' && name.value.trim() !== session.value?.name)

onMounted(async () => {
  try {
    const current = await refreshSession()
    if (current === null || current.username === null) {
      await router.replace({ name: 'login' })

      return
    }
    name.value = current.name
  } catch {
    // the account shown is the one the browser remembers
  }
})

async function save(): Promise<void> {
  if (sending.value || !changed.value) {
    return
  }
  sending.value = true
  error.value = null
  saved.value = false
  try {
    name.value = (await rename(name.value.trim())).name
    saved.value = true
  } catch (problem) {
    const errors = formErrors(problem)
    error.value = errors.name ?? errors.form ?? null
  } finally {
    sending.value = false
  }
}

async function leave(): Promise<void> {
  await signOut()
  await router.push({ name: 'landing' })
}
</script>

<template>
  <div v-if="session" class="account screen">
    <div class="heading">
      <div class="eyebrow">Your account</div>
      <h1>{{ session.name }}</h1>
      <p v-if="created" class="welcome" role="status">Your account is created. Welcome to court.</p>
    </div>

    <section class="rows" aria-label="Identity">
      <div class="form-label row-label">Username</div>
      <div class="row">
        <div class="username">{{ session.username }}</div>
        <p class="form-hint">How you sign in. Only you see it.</p>
      </div>

      <label class="form-label row-label" for="court-name">Name at court</label>
      <form class="row name" @submit.prevent="save">
        <div class="name-row">
          <input
            id="court-name"
            v-model="name"
            class="field"
            :maxlength="MAX_NAME_LENGTH"
            autocomplete="off"
            spellcheck="false"
            required
            @input="saved = false"
          />
          <button type="submit" class="btn btn-ghost" :disabled="sending || !changed">Save</button>
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <p v-else-if="saved" class="form-hint" role="status">Saved.</p>
        <p v-else class="form-hint">What the other players see at the table.</p>
      </form>

      <div class="form-label row-label linked-label">Linked accounts</div>
      <div class="row">
        <div class="linked">
          <button type="button" class="btn btn-ghost" aria-disabled="true">Link Discord</button>
          <button type="button" class="btn btn-ghost" aria-disabled="true">Link Google</button>
        </div>
        <p class="form-hint">Coming soon: sign in without a password, and recover your account.</p>
      </div>
    </section>

    <div class="actions">
      <RouterLink :to="{ name: 'queue' }" class="btn btn-primary">Play a game</RouterLink>
      <button type="button" class="btn-link" @click="leave">Sign out</button>
    </div>
  </div>
</template>

<style scoped>
.account {
  display: flex;
  flex-direction: column;
  gap: 36px;
  padding: 48px 96px;
  max-width: 1232px;
  width: 100%;
  margin: 0 auto;
}

.heading {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

h1 {
  font-size: 64px;
  line-height: 1;
  overflow-wrap: anywhere;
}

.welcome {
  font-size: 16px;
  color: var(--ink-2);
}

.rows {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  gap: 28px 40px;
  align-items: start;
  padding-top: 28px;
  border-top: 1px solid var(--line);
}

.row-label {
  padding-top: 14px;
}

.linked-label {
  padding-top: 12px;
}

.row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 560px;
}

.username {
  font-size: 20px;
  font-weight: 500;
  min-height: 48px;
  display: flex;
  align-items: center;
}

.name-row,
.linked {
  display: flex;
  gap: 10px;
}

.name-row .btn {
  flex: none;
}

.linked .btn {
  min-height: 44px;
  padding: 0 18px;
  font-size: 15px;
}

.actions {
  padding-top: 28px;
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  gap: 24px;
}
</style>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { ApiProblem } from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { formErrors } from '@/lib/credentials'
import { signIn } from '@/lib/session'

/**
 * Sign in, for a player who has an account: by their username and password.
 */
const router = useRouter()

const username = ref('')
const password = ref('')
const error = ref<string | null>(null)
const sending = ref(false)

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await signIn(username.value.trim(), password.value)
    await router.push({ name: 'account' })
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.status === 401) {
      // the same message whether the username exists or not
      error.value = 'Unknown username or wrong password.'
    } else if (problem instanceof ApiProblem && problem.status === 429) {
      error.value = 'Too many attempts. Wait a minute, then try again.'
    } else {
      error.value = formErrors(problem).form ?? 'Unknown username or wrong password.'
    }
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="credentials screen">
    <div class="mon">
      <ClanMon :src="enso" :size="300" />
    </div>
    <form class="form" @submit.prevent="submit">
      <div class="eyebrow">Return to court</div>
      <h1>Sign in</h1>
      <div class="form-field">
        <label for="username">Username</label>
        <input
          id="username"
          v-model="username"
          class="field field-ui"
          autocomplete="username"
          autocapitalize="off"
          spellcheck="false"
          required
        />
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <input
          id="password"
          v-model="password"
          class="field field-ui"
          type="password"
          autocomplete="current-password"
          required
        />
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      </div>
      <div class="actions">
        <button type="submit" class="btn btn-primary btn-large" :disabled="sending">Sign in</button>
        <span class="other">No account yet? <RouterLink :to="{ name: 'signup' }">Create one</RouterLink></span>
      </div>
    </form>
  </div>
</template>

<style scoped>
.credentials {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
  gap: 72px;
  align-items: center;
  padding: 40px 96px 56px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
}

.mon {
  display: flex;
  justify-content: center;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 26px;
  max-width: 520px;
}

h1 {
  font-size: 72px;
  line-height: 1;
}

.actions {
  display: flex;
  align-items: center;
  gap: 24px;
}

.other {
  font-size: 15px;
  color: var(--ink-2);
}

.other a {
  font-weight: 500;
  text-underline-offset: 4px;
}
</style>

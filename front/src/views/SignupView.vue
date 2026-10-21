<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { findClan } from '@/data/clans'
import { formErrors, passwordProblem, usernameProblem, type FormErrors } from '@/lib/credentials'
import { newcomer } from '@/lib/newcomer'
import { isGuest, session, signUp } from '@/lib/session'

/**
 * Creates an account: a username and a password, nothing else. A guest keeps their name at court and their games;
 * anyone else gets a name at court drawn at random, which they change from their account.
 */
const router = useRouter()

const guest = computed(() => (isGuest(session.value) ? session.value : null))
// the clan of the introductory game, while the tab remembers it
const clan = computed(() => (guest.value === null ? undefined : findClan(newcomer.clanId)))

const username = ref('')
const password = ref('')
const errors = ref<FormErrors>({})
const sending = ref(false)

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }
  const checked: FormErrors = {}
  const badUsername = usernameProblem(username.value)
  const badPassword = passwordProblem(password.value)
  if (badUsername !== null) {
    checked.username = badUsername
  }
  if (badPassword !== null) {
    checked.password = badPassword
  }
  errors.value = checked
  if (Object.keys(checked).length > 0) {
    return
  }

  sending.value = true
  try {
    await signUp(username.value, password.value)
    await router.push({ name: 'account', query: { created: '1' } })
  } catch (problem) {
    errors.value = formErrors(problem)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="credentials screen">
    <div class="mon">
      <ClanMon v-if="clan" :src="clan.mon" :color="clan.color" :size="300" :label="`${clan.name} Clan mon`" />
      <ClanMon v-else :src="enso" :size="300" />
    </div>
    <form class="form" novalidate @submit.prevent="submit">
      <template v-if="guest">
        <div class="eyebrow">Create your account</div>
        <h1>{{ guest.name }}</h1>
        <p class="lead">
          You keep your name at court and your games. Choose how you will sign in; you can change your name later from
          your account.
        </p>
      </template>
      <template v-else>
        <div class="eyebrow">Join the court</div>
        <h1>Create your account</h1>
        <p class="lead">
          Choose how you will sign in. The court gives you a name, which you can change from your account.
        </p>
      </template>
      <div class="form-field">
        <label for="username">Username</label>
        <input
          id="username"
          v-model="username"
          class="field field-ui"
          autocomplete="username"
          autocapitalize="off"
          spellcheck="false"
          maxlength="32"
          :aria-invalid="errors.username !== undefined"
          aria-describedby="username-hint"
        />
        <p v-if="errors.username" class="form-error" role="alert">{{ errors.username }}</p>
        <p id="username-hint" class="form-hint">3 to 32 letters, digits or _ . Only you see it.</p>
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <input
          id="password"
          v-model="password"
          class="field field-ui"
          type="password"
          autocomplete="new-password"
          :aria-invalid="errors.password !== undefined"
          aria-describedby="password-hint"
        />
        <p v-if="errors.password" class="form-error" role="alert">{{ errors.password }}</p>
        <p id="password-hint" class="form-hint">At least 8 characters.</p>
      </div>
      <p v-if="errors.form || errors.name" class="form-error" role="alert">{{ errors.form ?? errors.name }}</p>
      <div class="actions">
        <button type="submit" class="btn btn-primary btn-large" :disabled="sending">Create my account</button>
        <RouterLink :to="{ name: 'landing' }" class="btn-link">Not now</RouterLink>
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
  padding: 32px 96px 48px;
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
  gap: 24px;
  max-width: 520px;
  min-width: 0;
}

h1 {
  font-size: 72px;
  line-height: 1;
  overflow-wrap: anywhere;
}

.lead {
  font-size: 18px;
  line-height: 1.6;
  color: var(--ink-2);
}

.actions {
  display: flex;
  align-items: center;
  gap: 24px;
}
</style>

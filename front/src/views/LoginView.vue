<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { ApiProblem, type OAuthProvider } from '@/api/client'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import ProviderLogo from '@/components/ProviderLogo.vue'
import { formErrors } from '@/lib/credentials'
import { enabledProviders, isProvider, providerName, startOAuth } from '@/lib/oauth'
import { signIn } from '@/lib/session'

/**
 * Sign in, for a player who has an account: by their username and password, or with a provider (Google, Discord) their
 * account is linked to. A provider that sent the visitor back without signing them in says why (`?oauth=`).
 */
const route = useRoute()
const router = useRouter()

const providers = ref<OAuthProvider[]>([])
const leaving = ref<OAuthProvider | null>(null)
const providerError = ref<string | null>(null)

/** what the provider the visitor comes back from says */
const returned = computed(() => {
  const provider = route.query.provider
  if (providerError.value !== null || !isProvider(provider)) {
    return null
  }
  const name = providerName(provider)
  if (route.query.oauth === 'not_linked') {
    return {
      title: `No account is linked to this ${name} account.`,
      text: `Sign in with your username, then link ${name} from your account.`,
    }
  }
  if (route.query.oauth === 'failed') {
    return { title: `Something went wrong with ${name}: try again.`, text: null }
  }

  return null
})

onMounted(async () => {
  providers.value = await enabledProviders()
})

async function continueWith(provider: OAuthProvider): Promise<void> {
  if (leaving.value !== null) {
    return
  }
  leaving.value = provider
  providerError.value = null
  try {
    await startOAuth(provider, 'sign_in')
  } catch {
    providerError.value = `Something went wrong with ${providerName(provider)}: try again.`
    leaving.value = null
  }
}

const username = ref('')
const password = ref('')
const error = ref<string | null>(null)
const sending = ref(false)

/** where the visitor goes once signed in: a page of the site they came from (an invitation), else their account */
const next =
  typeof route.query.next === 'string' && route.query.next.startsWith('/') && !route.query.next.startsWith('//')
    ? route.query.next
    : null

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await signIn(username.value.trim(), password.value)
    await router.push(next ?? { name: 'account' })
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
    <div class="form">
      <div class="eyebrow">Return to court</div>
      <h1>Sign in</h1>
      <div v-if="returned" class="form-alert" role="alert">
        <p class="form-alert-title">{{ returned.title }}</p>
        <p v-if="returned.text" class="form-alert-text">{{ returned.text }}</p>
      </div>
      <form class="fields" @submit.prevent="submit">
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
      <template v-if="providers.length > 0">
        <div class="or" aria-hidden="true">
          <span class="rule" />
          <span>or, once your account is linked</span>
          <span class="rule" />
        </div>
        <div class="providers">
          <button
            v-for="provider in providers"
            :key="provider"
            type="button"
            class="btn btn-ghost btn-large"
            :disabled="leaving !== null"
            @click="continueWith(provider)"
          >
            <ProviderLogo :provider="provider" />
            <span>Continue with {{ providerName(provider) }}</span>
          </button>
        </div>
        <p v-if="providerError" class="form-error" role="alert">{{ providerError }}</p>
      </template>
    </div>
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

.form,
.fields {
  display: flex;
  flex-direction: column;
  gap: 22px;
  max-width: 520px;
}

h1 {
  font-size: 64px;
  line-height: 1;
}

.or {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 14px;
  color: var(--ink-3);
}

.rule {
  flex: 1;
  height: 1px;
  background: var(--line);
}

.providers {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.providers .btn {
  padding: 0 20px;
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

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiProblem, fetchInvitationStatus, joinQueue } from '@/api/client'
import ClanPicker from '@/components/ClanPicker.vue'
import { CLANS, findClan, type ClanId } from '@/data/clans'
import { drawCourtName, MAX_NAME_LENGTH } from '@/lib/names'
import { newcomer } from '@/lib/newcomer'
import { guestSession, session } from '@/lib/session'

/**
 * The link of an invitation of a friend (`/join/:code`): who invites, then the queue with the code. A visitor with a
 * session (an account, or the guest the browser keeps) chooses their deck in the queue; anyone else chooses a clan and
 * a name at court, becomes a guest and enters the queue with the starter deck of their clan.
 */
const route = useRoute()
const router = useRouter()

const code = typeof route.params.code === 'string' ? route.params.code : ''
/** null while the invitation is read */
const state = ref<'identity' | 'invalid' | null>(null)
const hostName = ref('')
const clanId = ref<ClanId | null>(newcomer.clanId ?? CLANS[Math.floor(Math.random() * CLANS.length)]!.id)
const clan = computed(() => findClan(clanId.value) ?? CLANS[0]!)
const name = ref(drawCourtName(clan.value))
const sending = ref(false)
const error = ref<string | null>(null)

// another clan, another family
watch(clan, (current) => (name.value = drawCourtName(current)))

onMounted(async () => {
  try {
    const status = await fetchInvitationStatus(code)
    hostName.value = status.host_name
    if (status.state !== 'pending') {
      state.value = 'invalid'

      return
    }
  } catch (problem) {
    if (!(problem instanceof ApiProblem)) {
      throw problem
    }
    state.value = 'invalid'

    return
  }

  if (session.value !== null) {
    await router.replace({ name: 'queue', query: { code } })

    return
  }
  state.value = 'identity'
})

async function enter(): Promise<void> {
  const chosenName = name.value.trim()
  if (chosenName === '' || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const guest = await guestSession(chosenName)
    newcomer.clanId = clan.value.id
    await joinQueue(guest.token, clan.value.deckId, { invitation_code: code })
    await router.push({ name: 'queue', query: { code } })
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.type === 'invalid_invitation') {
      state.value = 'invalid'
    } else {
      error.value = 'The queue cannot be joined: try again.'
    }
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <form v-if="state === 'identity'" class="join screen" @submit.prevent="enter">
    <div class="heading">
      <div class="eyebrow">A friendly game</div>
      <h1>{{ hostName }} invites you</h1>
      <p class="lead">Choose your clan and your name at court, then take your seat. You play its starter deck.</p>
    </div>
    <section class="clan" aria-labelledby="clan-label">
      <h2 id="clan-label" class="form-label">Your clan</h2>
      <ClanPicker v-model="clanId" label="Your clan" :disabled="sending" />
    </section>
    <section class="name">
      <label for="court-name" class="form-label">Name at court</label>
      <div class="form-field">
        <div class="field-row">
          <input
            id="court-name"
            v-model="name"
            class="field"
            :maxlength="MAX_NAME_LENGTH"
            autocomplete="off"
            spellcheck="false"
            required
          />
          <button type="button" class="btn btn-ghost" @click="name = drawCourtName(clan)">Draw another</button>
        </div>
        <p class="form-hint">What {{ hostName }} sees at the table.</p>
      </div>
    </section>
    <div class="actions">
      <button type="submit" class="btn btn-primary btn-large" :disabled="name.trim() === '' || sending">
        Enter the queue
      </button>
      <span class="with">
        with the <strong>{{ clan.name }} starter deck</strong>
      </span>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <RouterLink :to="{ name: 'login', query: { next: route.fullPath } }" class="btn-link aside">
        I have an account: sign in
      </RouterLink>
    </div>
  </form>

  <div v-else-if="state === 'invalid'" class="invalid screen">
    <div class="heading" role="status">
      <div class="eyebrow">A friendly game</div>
      <h1>This invitation is no longer valid</h1>
      <p class="lead">
        Someone has already taken the seat, or the host has left the queue, or the invitation has expired.
        <template v-if="hostName">Ask {{ hostName }} for a new link.</template>
        <template v-else>Ask for a new link.</template>
      </p>
    </div>
    <div>
      <RouterLink :to="{ name: 'landing' }" class="btn btn-primary btn-large">Play against the house bot</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.join {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 44px 96px 40px;
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

.lead {
  font-size: 16px;
  color: var(--ink-2);
}

.clan {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

h2.form-label {
  font-family: var(--font-ui);
}

.name {
  display: grid;
  grid-template-columns: 200px minmax(0, 560px);
  gap: 8px 40px;
  align-items: start;
}

.name > .form-label {
  padding-top: 14px;
}

.field-row {
  display: flex;
  gap: 10px;
}

.field-row .btn {
  flex: none;
}

.actions {
  margin-top: auto;
  padding-top: 24px;
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  gap: 24px;
}

.with {
  font-size: 15px;
  color: var(--ink-2);
}

.with strong {
  font-family: var(--font-display);
  font-size: 18px;
}

.aside {
  margin-left: auto;
}

.invalid {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 28px;
  padding: 40px 96px 96px;
  max-width: 880px;
}

.invalid .lead {
  font-size: 18px;
  line-height: 1.5;
}
</style>

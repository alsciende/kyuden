<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import {
  ApiProblem,
  cancelInvitation,
  createInvitation,
  fetchSelfInvitation,
  type Invitation,
  type InvitationKind,
} from '@/api/client'
import { BOT_PROMPT, invitationLink, keepBotKey, keptBotKey, mcpConfiguration } from '@/lib/invitation'
import { session } from '@/lib/session'

/**
 * A friendly game: the account invites its bot or a friend, gives them what they need (the configuration of an MCP
 * client with the key of the bot, shown once; the link for a friend), then chooses its deck and enters the queue with
 * the invitation, where it is matched with its guest only.
 */
const router = useRouter()

/** null while the invitation waiting is read */
const invitation = ref<Invitation | null>(null)
const loading = ref(true)
const sending = ref(false)
const error = ref<string | null>(null)
/** what was just copied, to say so */
const copied = ref<string | null>(null)

const botKey = computed(() => (invitation.value ? keptBotKey(invitation.value.id) : null))
const configuration = computed(() => (botKey.value ? mcpConfiguration(location.origin, botKey.value) : null))
const link = computed(() => (invitation.value?.code ? invitationLink(location.origin, invitation.value.code) : null))

onMounted(async () => {
  const token = session.value?.token
  if (!token) {
    return
  }
  try {
    invitation.value = await fetchSelfInvitation(token)
  } catch (problem) {
    if (!(problem instanceof ApiProblem) || problem.status !== 404) {
      error.value = 'Your invitation cannot be read.'
    }
  } finally {
    loading.value = false
  }
})

async function invite(kind: InvitationKind): Promise<void> {
  const token = session.value?.token
  if (!token || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const created = await createInvitation(token, kind)
    if (created.token !== null) {
      keepBotKey(created.id, created.token)
    }
    invitation.value = created
  } catch {
    error.value = 'The invitation cannot be created.'
  } finally {
    sending.value = false
  }
}

async function cancel(): Promise<void> {
  const token = session.value?.token
  if (!token || sending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    await cancelInvitation(token)
  } catch (problem) {
    // already cancelled, or used
    if (!(problem instanceof ApiProblem) || problem.status !== 404) {
      error.value = 'The invitation cannot be cancelled.'
      sending.value = false

      return
    }
  }
  invitation.value = null
  sending.value = false
}

async function copy(what: string, text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = what
  } catch {
    copied.value = null
  }
}

function chooseDeck(): void {
  if (invitation.value) {
    void router.push({ name: 'queue', query: { invitation: invitation.value.id } })
  }
}
</script>

<template>
  <div v-if="!loading" class="invite screen">
    <template v-if="invitation === null">
      <div class="heading">
        <div class="eyebrow">A friendly game</div>
        <h1>Whom do you invite?</h1>
        <p class="lead">
          A friendly game is not ranked. You send an invitation, then you both enter the queue: you are matched
          together, and with nobody else.
        </p>
      </div>
      <div class="options">
        <button type="button" class="option" :disabled="sending" @click="invite('bot')">
          <span class="option-name">Your bot</span>
          <span class="option-text">
            A program that plays for you through the MCP server of Kyūden, such as an AI agent. You get a key to give
            it: it plays as your bot, in this game only.
          </span>
          <span class="option-go">Create a key for your bot</span>
        </button>
        <button type="button" class="option" :disabled="sending" @click="invite('friend')">
          <span class="option-name">A friend</span>
          <span class="option-text">
            Anyone you send the link to: a player with an account, or someone who has never played here. The first one
            to use it takes the seat.
          </span>
          <span class="option-go">Create a link for a friend</span>
        </button>
      </div>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    </template>

    <template v-else-if="invitation.kind === 'bot'">
      <div class="heading">
        <div class="eyebrow">A friendly game · Your bot</div>
        <h1>Give this key to your bot</h1>
        <p class="lead">
          It plays as <strong>{{ invitation.bot?.name }}</strong
          >, in this game only.
          <template v-if="botKey">Copy it now: it is shown once.</template>
        </p>
      </div>
      <section v-if="botKey && configuration" class="needs" aria-label="What your bot needs">
        <div class="column">
          <div class="label-row">
            <h2 class="form-label">MCP configuration</h2>
            <button type="button" class="btn btn-ghost btn-small" @click="copy('configuration', configuration)">
              {{ copied === 'configuration' ? 'Copied' : 'Copy' }}
            </button>
          </div>
          <pre class="code block">{{ configuration }}</pre>
          <p class="form-hint">
            For any MCP client: Claude Code, Claude Desktop… Valid until the end of the game, or for 24 hours if nobody
            comes. Leaving the queue cancels it.
          </p>
        </div>
        <div class="column">
          <div class="form-field">
            <h2 class="form-label">Prompt</h2>
            <div class="copy-row">
              <output class="code prompt">{{ BOT_PROMPT }}</output>
              <button type="button" class="btn btn-ghost" @click="copy('prompt', BOT_PROMPT)">
                {{ copied === 'prompt' ? 'Copied' : 'Copy' }}
              </button>
            </div>
            <p class="form-hint">The rules and how to play come from the server.</p>
          </div>
          <div class="form-field">
            <h2 class="form-label">Key only</h2>
            <div class="copy-row">
              <output class="code">{{ botKey }}</output>
              <button type="button" class="btn btn-ghost" @click="copy('key', botKey)">
                {{ copied === 'key' ? 'Copied' : 'Copy' }}
              </button>
            </div>
            <p class="form-hint">For a client you configure by hand: Authorization: Bearer &lt;key&gt;.</p>
          </div>
        </div>
      </section>
      <div v-else class="form-alert" role="status">
        <span class="form-alert-title">The key was shown once</span>
        <span class="form-alert-text">
          If your bot does not have it, cancel this invitation and invite your bot again: it gets a new key.
        </span>
      </div>
      <section class="then" aria-labelledby="then">
        <h2 id="then" class="form-label">Then</h2>
        <ol class="steps">
          <li>Start your bot. It joins the queue with the key and tells you when it is there.</li>
          <li>Choose your deck and enter the queue: you face your bot, whoever comes first.</li>
        </ol>
      </section>
    </template>

    <template v-else>
      <div class="heading">
        <div class="eyebrow">A friendly game · A friend</div>
        <h1>Send this link to your friend</h1>
        <p class="lead">
          No account needed on their side: they choose a name at court and a clan on the way. The first one to use the
          link takes the seat.
        </p>
      </div>
      <section class="form-field link" aria-labelledby="link">
        <h2 id="link" class="form-label">Invitation</h2>
        <div class="copy-row">
          <output class="code large">{{ link }}</output>
          <button type="button" class="btn btn-ghost btn-large" @click="link && copy('link', link)">
            {{ copied === 'link' ? 'Copied' : 'Copy the link' }}
          </button>
        </div>
        <p class="form-hint">Valid for 24 hours, used once. Leaving the queue cancels it.</p>
      </section>
      <section class="then" aria-labelledby="then">
        <h2 id="then" class="form-label">Then</h2>
        <ol class="steps">
          <li>Your friend opens the link, chooses a deck and enters the queue.</li>
          <li>Choose your deck and enter the queue: you face your friend, whoever comes first.</li>
        </ol>
      </section>
    </template>

    <div v-if="invitation !== null" class="actions">
      <button type="button" class="btn btn-primary btn-large" :disabled="sending" @click="chooseDeck">
        Choose your deck
      </button>
      <button type="button" class="btn-link" :disabled="sending" @click="cancel">Cancel the invitation</button>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    </div>
    <div v-else class="actions">
      <RouterLink :to="{ name: 'queue' }" class="btn-link">Or play a ranked game</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.invite {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 30px;
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
}

.lead {
  font-size: 16px;
  color: var(--ink-2);
  max-width: 760px;
}

.lead strong {
  font-family: var(--font-display);
  font-size: 18px;
}

h2.form-label {
  font-family: var(--font-ui);
}

.options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 28px;
  max-width: 1120px;
  padding-top: 28px;
  border-top: 1px solid var(--line);
}

.option {
  font: inherit;
  color: var(--ink);
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 32px 32px 28px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
  cursor: pointer;
}

.option:hover {
  border-color: var(--ink);
}

.option:disabled {
  cursor: wait;
}

.option-name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 34px;
  line-height: 1.1;
}

.option-text {
  font-size: 16px;
  line-height: 1.5;
  color: var(--ink-2);
}

.option-go {
  margin-top: auto;
  font-weight: 700;
  font-size: 15px;
  color: var(--seal);
}

.needs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 40px;
  max-width: 1344px;
  padding-top: 26px;
  border-top: 1px solid var(--line);
}

.column {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.column + .column {
  gap: 24px;
}

.label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.btn-small {
  min-height: 44px;
  padding: 0 18px;
  font-size: 15px;
}

.copy-row {
  display: flex;
  gap: 10px;
}

.copy-row .btn {
  flex: none;
}

.code {
  font-family: ui-monospace, 'SF Mono', Menlo, monospace;
  font-size: 15px;
  color: var(--ink);
  background: var(--card);
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  padding: 0 16px;
  min-height: 48px;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  overflow-x: auto;
  white-space: nowrap;
}

.code.block {
  display: block;
  margin: 0;
  padding: 14px 18px;
  font-size: 14px;
  line-height: 1.4;
  white-space: pre;
}

.code.prompt {
  font-family: var(--font-ui);
  font-size: 16px;
}

.code.large {
  font-size: 19px;
  min-height: 56px;
}

.link {
  max-width: 1040px;
  padding-top: 26px;
  border-top: 1px solid var(--line);
}

.then {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

.steps {
  margin: 0;
  padding: 0;
  list-style: none;
  counter-reset: step;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 32px;
  max-width: 1120px;
}

.steps li {
  counter-increment: step;
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  gap: 14px;
  align-items: baseline;
  font-size: 16px;
  line-height: 1.5;
}

.steps li::before {
  content: counter(step);
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 26px;
  color: var(--seal);
}

.actions {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 24px;
}
</style>

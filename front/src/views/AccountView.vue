<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import {
  fetchLinkedAccounts,
  unlinkAccount,
  type CardDisplay,
  type LinkedAccount,
  type OAuthProvider,
  type Theme,
} from '@/api/client'
import ClanMon from '@/components/ClanMon.vue'
import ClanPicker from '@/components/ClanPicker.vue'
import PlayerNobori from '@/components/PlayerNobori.vue'
import ProviderLogo from '@/components/ProviderLogo.vue'
import type { ClanId } from '@/data/clans'
import { MONS, PATTERNS, type Mon, type Pattern } from '@/data/nobori'
import { MAX_NAME_LENGTH } from '@/lib/names'
import { chooseNobori, earnedCount, isEarned, loadOwnNobori, type OwnNobori } from '@/lib/nobori'
import { formErrors } from '@/lib/credentials'
import { enabledProviders, isProvider, PROVIDERS, providerName, startOAuth } from '@/lib/oauth'
import {
  chooseCardDisplay,
  chooseTheme,
  deleteAccount,
  preferClan,
  refreshSession,
  rename,
  session,
  signOut,
} from '@/lib/session'

/**
 * The account of the player: their username (how they sign in), their preferred clan, their name at court (what the
 * other players see), their nobori (a mon on a pattern, among those they have earned: the others are shown locked), how
 * the board draws the cards and whether the site is light or dark, which they change here;
 * and the providers (Google, Discord) they link it to, to sign in with them. A provider that sent the player back
 * after linking says how it went (`?oauth=`). Last, they can delete it, once confirmed.
 */
const route = useRoute()
const router = useRouter()

const created = computed(() => route.query.created === '1')
const name = ref(session.value?.name ?? '')
const saved = ref(false)
const error = ref<string | null>(null)
const sending = ref(false)

const clanId = ref<ClanId | null>(session.value?.preferredClan ?? null)
const clanSaved = ref(false)
const clanError = ref<string | null>(null)
const clanSending = ref(false)

const CARD_DISPLAYS: { value: CardDisplay; label: string; hint: string }[] = [
  { value: 'drawn', label: 'Drawn', hint: 'The name and the values of each card, easy to read at a glance.' },
  { value: 'images', label: 'Card images', hint: 'The printed cards, as on a real table.' },
]

const cardDisplay = ref<CardDisplay>(session.value?.cardDisplay ?? 'images')
const displaySaved = ref(false)
const displayError = ref<string | null>(null)
const displaySending = ref(false)

const THEMES: { value: Theme; label: string; hint: string }[] = [
  { value: 'light', label: 'Day', hint: 'Ink on paper.' },
  { value: 'dark', label: 'Night', hint: 'Light ink on a dark ground.' },
  { value: 'auto', label: 'Auto', hint: 'As your system: day or night.' },
]

const theme = ref<Theme>(session.value?.theme ?? 'auto')
const themeSaved = ref(false)
const themeError = ref<string | null>(null)
const themeSending = ref(false)

const nobori = ref<OwnNobori | null>(null)
const noboriSending = ref(false)
const noboriSaved = ref(false)
const noboriError = ref<string | null>(null)
/** what a click on a mon or a pattern not earned yet tells, under its row */
const monHint = ref<string | null>(null)
const patternHint = ref<string | null>(null)

const chosenMon = computed(() => MONS.find((mon) => mon.id === nobori.value?.banner.mon) ?? MONS[0]!)
const chosenPattern = computed(
  () => PATTERNS.find((pattern) => pattern.id === nobori.value?.banner.pattern) ?? PATTERNS[0]!,
)

function earned(id: Mon['id'] | Pattern['id']): boolean {
  return nobori.value !== null && isEarned(nobori.value.unlocked, id)
}

async function changeNobori(changes: { mon?: Mon['id']; pattern?: Pattern['id'] }): Promise<void> {
  const token = session.value?.token
  if (token === undefined || noboriSending.value) {
    return
  }
  noboriSending.value = true
  noboriError.value = null
  noboriSaved.value = false
  try {
    nobori.value = await chooseNobori(token, changes)
    noboriSaved.value = true
  } catch (problem) {
    noboriError.value = formErrors(problem).form ?? 'Your nobori could not be saved.'
  } finally {
    noboriSending.value = false
  }
}

function pickMon(mon: Mon): void {
  if (!earned(mon.id)) {
    monHint.value = `${mon.label} · Not earned yet`

    return
  }
  monHint.value = null
  if (mon.id !== nobori.value?.banner.mon) {
    void changeNobori({ mon: mon.id })
  }
}

function pickPattern(pattern: Pattern): void {
  if (!earned(pattern.id)) {
    patternHint.value = `${pattern.name} · Not earned yet`

    return
  }
  patternHint.value = null
  if (pattern.id !== nobori.value?.banner.pattern) {
    void changeNobori({ pattern: pattern.id })
  }
}

const changed = computed(() => name.value.trim() !== '' && name.value.trim() !== session.value?.name)

const providers = ref<OAuthProvider[]>([])
const linkedAccounts = ref<LinkedAccount[]>([])
const linkSending = ref<OAuthProvider | null>(null)
const confirming = ref<OAuthProvider | null>(null)
const linkStatus = ref<string | null>(null)
const linkError = ref<string | null>(null)
/** the provider whose identity is linked to another account, which the player can sign in to instead */
const linkedElsewhere = ref<OAuthProvider | null>(null)

const returnedProvider = route.query.provider
if (isProvider(returnedProvider)) {
  const returnedName = providerName(returnedProvider)
  if (route.query.oauth === 'linked') {
    linkStatus.value = `${returnedName} is linked: you can sign in with it.`
  } else if (route.query.oauth === 'linked_elsewhere') {
    linkedElsewhere.value = returnedProvider
  } else if (route.query.oauth === 'failed') {
    linkError.value = `Something went wrong with ${returnedName}: try again.`
  }
}

const LINKED_ON = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })

/** the enabled providers, and those linked whatever their state (to unlink them) */
const linkRows = computed(() =>
  PROVIDERS.flatMap(({ id, name }) => {
    const linked = linkedAccounts.value.find((account) => account.provider === id)
    if (linked === undefined && !providers.value.includes(id)) {
      return []
    }

    return [{ id, name, linkedOn: linked === undefined ? null : LINKED_ON.format(new Date(linked.linked_at)) }]
  }),
)

async function loadLinkedAccounts(token: string): Promise<void> {
  const [enabled, linked] = await Promise.all([enabledProviders(), fetchLinkedAccounts(token)])
  providers.value = enabled
  linkedAccounts.value = linked
}

async function link(provider: OAuthProvider): Promise<void> {
  if (linkSending.value !== null) {
    return
  }
  linkSending.value = provider
  linkError.value = null
  linkStatus.value = null
  linkedElsewhere.value = null
  try {
    await startOAuth(provider, 'link')
  } catch {
    linkError.value = `Something went wrong with ${providerName(provider)}: try again.`
    linkSending.value = null
  }
}

async function unlink(provider: OAuthProvider): Promise<void> {
  const token = session.value?.token
  if (linkSending.value !== null || token === undefined) {
    return
  }
  linkSending.value = provider
  linkError.value = null
  linkStatus.value = null
  try {
    await unlinkAccount(token, provider)
    linkedAccounts.value = linkedAccounts.value.filter((account) => account.provider !== provider)
    linkStatus.value = `${providerName(provider)} is unlinked.`
    confirming.value = null
  } catch {
    linkError.value = `${providerName(provider)} could not be unlinked: try again.`
  } finally {
    linkSending.value = null
  }
}

/**
 * Leaves this account for the one the identity at the provider is linked to.
 */
async function signInInstead(provider: OAuthProvider): Promise<void> {
  await signOut()
  try {
    await startOAuth(provider, 'sign_in')
  } catch {
    await router.push({ name: 'login', query: { oauth: 'failed', provider } })
  }
}

onMounted(async () => {
  try {
    const current = await refreshSession()
    if (current === null || current.username === null) {
      await router.replace({ name: 'login' })

      return
    }
    name.value = current.name
    clanId.value = current.preferredClan
    cardDisplay.value = current.cardDisplay
    theme.value = current.theme
  } catch {
    // the account shown is the one the browser remembers
  }
  if (route.query.oauth !== undefined) {
    // told once: reloading the page does not tell it again
    await router.replace({ query: { ...route.query, oauth: undefined, provider: undefined } })
  }
  if (session.value !== null) {
    try {
      nobori.value = await loadOwnNobori(session.value.token)
    } catch {
      // no nobori shown
    }
    try {
      await loadLinkedAccounts(session.value.token)
    } catch {
      // no linked accounts shown
    }
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

async function changeClan(id: ClanId | null): Promise<void> {
  if (id === null || id === session.value?.preferredClan) {
    return
  }
  clanSending.value = true
  clanError.value = null
  clanSaved.value = false
  try {
    clanId.value = (await preferClan(id)).preferredClan
    clanSaved.value = true
  } catch (problem) {
    clanId.value = session.value?.preferredClan ?? null
    const errors = formErrors(problem)
    clanError.value = errors.clan ?? errors.form ?? null
  } finally {
    clanSending.value = false
  }
}

async function changeCardDisplay(value: CardDisplay): Promise<void> {
  if (value === session.value?.cardDisplay) {
    return
  }
  displaySending.value = true
  displayError.value = null
  displaySaved.value = false
  try {
    cardDisplay.value = (await chooseCardDisplay(value)).cardDisplay
    displaySaved.value = true
  } catch (problem) {
    cardDisplay.value = session.value?.cardDisplay ?? 'images'
    displayError.value = formErrors(problem).form ?? 'The setting could not be saved.'
  } finally {
    displaySending.value = false
  }
}

async function changeTheme(value: Theme): Promise<void> {
  if (value === session.value?.theme) {
    return
  }
  themeSending.value = true
  themeError.value = null
  themeSaved.value = false
  try {
    theme.value = (await chooseTheme(value)).theme
    themeSaved.value = true
  } catch (problem) {
    theme.value = session.value?.theme ?? 'auto'
    themeError.value = formErrors(problem).form ?? 'The setting could not be saved.'
  } finally {
    themeSending.value = false
  }
}

const confirmingDeletion = ref(false)
const deleting = ref(false)
const deletionError = ref<string | null>(null)

async function remove(): Promise<void> {
  if (deleting.value) {
    return
  }
  deleting.value = true
  deletionError.value = null
  try {
    await deleteAccount()
    await router.push({ name: 'landing' })
  } catch {
    deletionError.value = 'Your account could not be deleted: try again.'
    deleting.value = false
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
      <PlayerNobori v-if="nobori" :banner="nobori.banner" :width="64" class="heading-nobori" />
      <div class="heading-text">
        <div class="eyebrow">Your account</div>
        <h1>{{ session.name }}</h1>
        <p v-if="created" class="welcome" role="status">Your account is created. Welcome to court.</p>
      </div>
    </div>

    <section class="rows" aria-label="Identity">
      <div class="form-label row-label">Username</div>
      <div class="row">
        <div class="username">{{ session.username }}</div>
        <p class="form-hint">How you sign in. Only you see it.</p>
      </div>

      <div class="form-label row-label clan-label" aria-hidden="true">Preferred clan</div>
      <div class="row">
        <ClanPicker
          :model-value="clanId"
          label="Preferred clan"
          :disabled="clanSending"
          @update:model-value="changeClan"
        />
        <p v-if="clanError" class="form-error" role="alert">{{ clanError }}</p>
        <p v-else-if="clanSaved" class="form-hint" role="status">Saved.</p>
        <p v-else class="form-hint">You can change it at any time.</p>
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

      <template v-if="nobori">
        <div class="form-label row-label nobori-label">Nobori</div>
        <div class="row nobori-row">
          <div class="nobori-preview">
            <PlayerNobori :banner="nobori.banner" :width="60" class="preview-nobori" />
            <span class="preview-name">{{ session.name }}</span>
          </div>
          <p class="form-hint">What the other players see when they open your name at the table.</p>

          <div class="nobori-group">
            <div id="mon-label" class="nobori-title">
              Mon <span class="nobori-count">· {{ earnedCount(nobori.unlocked.mons, MONS) }}</span>
            </div>
            <div class="tiles" role="radiogroup" aria-labelledby="mon-label">
              <button
                v-for="mon in MONS"
                :key="mon.id"
                type="button"
                role="radio"
                class="tile"
                :class="{ locked: !earned(mon.id) }"
                :aria-checked="mon.id === nobori.banner.mon"
                :aria-disabled="!earned(mon.id) || undefined"
                :aria-label="earned(mon.id) ? mon.label : `${mon.label}, not earned yet`"
                :disabled="noboriSending"
                @click="pickMon(mon)"
              >
                <span v-if="mon.ringed" class="tile-ring" :style="{ borderColor: mon.color }">
                  <ClanMon :src="mon.src" :color="mon.color" :size="34" />
                </span>
                <ClanMon v-else :src="mon.src" :color="mon.color" :size="38" />
                <svg v-if="!earned(mon.id)" class="lock" width="11" height="11" viewBox="0 0 24 24" aria-hidden="true">
                  <rect width="18" height="11" x="3" y="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </button>
            </div>
            <p class="form-hint" aria-live="polite">{{ monHint ?? chosenMon.label }}</p>
          </div>

          <div class="nobori-group">
            <div id="pattern-label" class="nobori-title">
              Pattern <span class="nobori-count">· {{ earnedCount(nobori.unlocked.patterns, PATTERNS) }}</span>
            </div>
            <div class="swatches" role="radiogroup" aria-labelledby="pattern-label">
              <button
                v-for="pattern in PATTERNS"
                :key="pattern.id"
                type="button"
                role="radio"
                class="swatch"
                :class="{ locked: !earned(pattern.id) }"
                :aria-checked="pattern.id === nobori.banner.pattern"
                :aria-disabled="!earned(pattern.id) || undefined"
                :aria-label="earned(pattern.id) ? pattern.name : `${pattern.name}, not earned yet`"
                :disabled="noboriSending"
                @click="pickPattern(pattern)"
              >
                <PlayerNobori
                  :banner="{ mon: 'enso', pattern: pattern.id }"
                  :width="28"
                  bare
                  decorative
                  class="swatch-nobori"
                />
                <svg
                  v-if="!earned(pattern.id)"
                  class="lock"
                  width="11"
                  height="11"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <rect width="18" height="11" x="3" y="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </button>
            </div>
            <p class="form-hint" aria-live="polite">{{ patternHint ?? chosenPattern.name }}</p>
          </div>
          <p v-if="noboriError" class="form-error" role="alert">{{ noboriError }}</p>
          <p v-else-if="noboriSaved" class="form-hint" role="status">Saved.</p>
          <p v-else class="form-hint">Saved as soon as you choose.</p>
        </div>
      </template>

      <div id="card-display-label" class="form-label row-label display-label">Cards on the board</div>
      <div class="row">
        <div class="choices" role="radiogroup" aria-labelledby="card-display-label">
          <label v-for="choice in CARD_DISPLAYS" :key="choice.value" class="choice">
            <input
              type="radio"
              name="card-display"
              :value="choice.value"
              :checked="cardDisplay === choice.value"
              :disabled="displaySending"
              @change="changeCardDisplay(choice.value)"
            />
            <span class="choice-text">
              <span class="choice-label">{{ choice.label }}</span>
              <span class="form-hint">{{ choice.hint }}</span>
            </span>
          </label>
        </div>
        <p v-if="displayError" class="form-error" role="alert">{{ displayError }}</p>
        <p v-else-if="displaySaved" class="form-hint" role="status">Saved.</p>
      </div>

      <div id="theme-label" class="form-label row-label display-label">Appearance</div>
      <div class="row">
        <div class="choices" role="radiogroup" aria-labelledby="theme-label">
          <label v-for="choice in THEMES" :key="choice.value" class="choice">
            <input
              type="radio"
              name="theme"
              :value="choice.value"
              :checked="theme === choice.value"
              :disabled="themeSending"
              @change="changeTheme(choice.value)"
            />
            <span class="choice-text">
              <span class="choice-label">{{ choice.label }}</span>
              <span class="form-hint">{{ choice.hint }}</span>
            </span>
          </label>
        </div>
        <p v-if="themeError" class="form-error" role="alert">{{ themeError }}</p>
        <p v-else-if="themeSaved" class="form-hint" role="status">Saved.</p>
      </div>

      <template v-if="linkRows.length > 0">
        <div class="form-label row-label linked-label">Linked accounts</div>
        <div class="row">
          <ul class="linked">
            <li v-for="row in linkRows" :key="row.id" class="provider" :class="{ confirming: confirming === row.id }">
              <template v-if="confirming === row.id">
                <div class="provider-line">
                  <ProviderLogo :provider="row.id" :size="24" />
                  <div class="provider-text">
                    <span class="provider-name">Unlink {{ row.name }}?</span>
                    <span class="form-hint">You will no longer sign in with it. Your password stays.</span>
                  </div>
                </div>
                <div class="confirm-actions">
                  <button type="button" class="btn btn-seal" :disabled="linkSending !== null" @click="unlink(row.id)">
                    Unlink {{ row.name }}
                  </button>
                  <button type="button" class="btn-link" @click="confirming = null">Keep it</button>
                </div>
              </template>
              <div v-else class="provider-line">
                <ProviderLogo :provider="row.id" :size="24" />
                <div class="provider-text">
                  <span class="provider-name">{{ row.name }}</span>
                  <span class="form-hint">{{ row.linkedOn ? `Linked on ${row.linkedOn}` : 'Not linked' }}</span>
                </div>
                <button v-if="row.linkedOn" type="button" class="btn-link" @click="confirming = row.id">Unlink</button>
                <button
                  v-else
                  type="button"
                  class="btn btn-ghost"
                  :disabled="linkSending !== null"
                  @click="link(row.id)"
                >
                  Link {{ row.name }}
                </button>
              </div>
            </li>
          </ul>
          <div v-if="linkedElsewhere" class="form-alert" role="alert">
            <p class="form-alert-title">
              This {{ providerName(linkedElsewhere) }} account is already linked to another Kyūden account.
            </p>
            <p class="form-alert-text">
              Sign in with {{ providerName(linkedElsewhere) }} to play with that account. To link
              {{ providerName(linkedElsewhere) }} here instead, unlink it from the other account first.
            </p>
            <div>
              <button type="button" class="btn btn-ghost" @click="signInInstead(linkedElsewhere)">
                Sign in with {{ providerName(linkedElsewhere) }} instead
              </button>
            </div>
          </div>
          <p v-if="linkError" class="form-error" role="alert">{{ linkError }}</p>
          <p v-else-if="linkStatus" class="form-hint status" role="status">{{ linkStatus }}</p>
          <p class="form-hint">Sign in without your password, and recover your account if you forget it.</p>
        </div>
      </template>

      <div class="form-label row-label deletion-label">Delete account</div>
      <div class="row">
        <div v-if="confirmingDeletion" class="deletion">
          <div class="deletion-text">
            <span class="deletion-title">Delete your account?</span>
            <span class="form-hint">
              Your username, your decks, your settings and your linked accounts are deleted, and so are your bots. Your
              games in progress are conceded. Your games stay for your opponents, under the name “A former player”. This
              cannot be undone.
            </span>
          </div>
          <div class="confirm-actions">
            <button type="button" class="btn btn-seal" :disabled="deleting" @click="remove">Delete my account</button>
            <button type="button" class="btn-link" @click="confirmingDeletion = false">Keep it</button>
          </div>
        </div>
        <div v-else>
          <button type="button" class="btn btn-ghost" @click="confirmingDeletion = true">Delete my account</button>
        </div>
        <p v-if="deletionError" class="form-error" role="alert">{{ deletionError }}</p>
      </div>
    </section>

    <div class="actions">
      <RouterLink :to="{ name: 'queue' }" class="btn btn-primary">Play a game</RouterLink>
      <RouterLink :to="{ name: 'invite' }" class="btn btn-ghost">Play a friendly game</RouterLink>
      <RouterLink :to="{ name: 'history' }" class="btn btn-ghost">Your games</RouterLink>
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
  align-items: stretch;
  gap: 22px;
}

.heading-text {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

/* the nobori takes the height of the heading, at least its mon and a length of its pattern */
.heading-nobori {
  min-height: 132px;
  border: 1px solid var(--line);
}

.nobori-label {
  padding-top: 18px;
}

.nobori-row {
  gap: 20px;
}

.nobori-preview {
  width: 300px;
  min-height: 92px;
  display: flex;
  padding: 2px;
  box-sizing: border-box;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 6px;
  box-shadow: 0 6px 20px rgb(31 27 22 / 0.1);
}

.preview-nobori {
  border-radius: 4px;
}

.preview-name {
  padding: 14px;
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 600;
  line-height: 1.1;
  overflow-wrap: anywhere;
}

.nobori-row > .form-hint:nth-child(2) {
  margin-top: -10px;
}

.nobori-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.nobori-title {
  font-size: 15px;
  font-weight: 600;
}

.nobori-count {
  font-weight: 400;
  color: var(--ink-3);
}

.tiles,
.swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 4px 0 0 4px;
}

.swatches {
  gap: 12px;
}

.tile,
.swatch {
  position: relative;
  font: inherit;
  padding: 0;
  cursor: pointer;
}

.tile {
  width: 56px;
  height: 56px;
  box-sizing: border-box;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--nobori-paper);
  display: flex;
  align-items: center;
  justify-content: center;
}

.swatch {
  border: 0;
  background: none;
}

.tile-ring {
  width: 38px;
  height: 38px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 3px solid transparent;
  border-radius: 50%;
}

.swatch-nobori {
  height: 72px;
  border: 1px solid var(--line);
}

.tile[aria-checked='true'] {
  outline: 2px solid var(--seal);
  outline-offset: 2px;
}

.swatch[aria-checked='true'] .swatch-nobori {
  outline: 2px solid var(--seal);
  outline-offset: 3px;
}

/* not earned yet: dimmed, a padlock at its corner; a click tells it under the row */
.tile.locked > :not(.lock),
.swatch.locked > :not(.lock) {
  opacity: 0.32;
}

.tile.locked,
.swatch.locked {
  cursor: help;
}

.lock {
  position: absolute;
  right: -6px;
  bottom: -6px;
  width: 20px;
  height: 20px;
  box-sizing: border-box;
  padding: 3px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  fill: none;
  stroke: var(--ink);
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
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

.clan-label {
  padding-top: 32px;
}

.linked-label {
  padding-top: 18px;
}

.display-label {
  padding-top: 2px;
}

.deletion-label {
  padding-top: 14px;
}

.deletion {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px 0 18px;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.deletion-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.deletion-title {
  font-size: 17px;
  font-weight: 700;
}

.deletion .confirm-actions {
  padding-left: 0;
}

.choices {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.choice {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
}

.choice input {
  flex: none;
  width: 18px;
  height: 18px;
  margin: 2px 0 0;
  accent-color: var(--seal);
  cursor: pointer;
}

.choice-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.choice-label {
  font-size: 17px;
  font-weight: 500;
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

.name-row {
  display: flex;
  gap: 10px;
}

.name-row .btn {
  flex: none;
}

.linked {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  border-top: 1px solid var(--line);
}

.provider {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;
  min-height: 64px;
  border-bottom: 1px solid var(--line);
}

.provider.confirming {
  padding: 16px 0 18px;
}

.provider-line {
  display: flex;
  align-items: center;
  gap: 16px;
}

.provider-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.provider-name {
  font-size: 17px;
  font-weight: 700;
}

.provider .btn {
  flex: none;
  font-size: 15px;
  padding: 0 22px;
}

.confirm-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-left: 40px;
}

.btn-seal {
  background: var(--seal);
  border-color: var(--seal);
  color: var(--seal-ink);
}

@media (hover: hover) {
  .btn-seal:hover {
    background: var(--seal);
    color: var(--seal-ink);
    filter: brightness(0.92);
  }
}

.status {
  color: var(--ink);
}

.actions {
  padding-top: 28px;
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  gap: 24px;
}
</style>

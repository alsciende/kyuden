<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import {
  ApiProblem,
  fetchSelfGames,
  joinQueueWithMatch,
  type Tournament,
  type TournamentEntry,
  type UserGame,
} from '@/api/client'
import { sendMessage } from '@/api/game'
import { checkQueue, startWaiting, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'
import { countdownLabel, currentMatchOf, roundTitle } from '@/lib/tournaments'
import { useLeaveQueue } from '@/lib/useLeaveQueue'

/**
 * The match of the player in the current round, at the top of the page of a tournament in progress: whom they face and
 * the time left to join the queue with it (10 minutes from the start of the round, else they forfeit), the queue joined
 * from here (a game in progress conceded first, once confirmed), the wait and leaving it, the game once it has begun;
 * or where they stand between two rounds, or out of the tournament.
 */
const props = defineProps<{ tournament: Tournament; entry: TournamentEntry; now: number }>()
const emit = defineEmits<{ changed: [] }>()

const userId = computed(() => session.value?.userId ?? null)
const match = computed(() => currentMatchOf(props.tournament, userId.value))
const opponent = computed(() => match.value?.players.find((player) => player.user_id !== userId.value) ?? null)
const round = computed(() => roundTitle(props.tournament.current_round, props.tournament.round_count))
const deadline = computed(() => props.tournament.join_deadline)
const late = computed(() => deadline.value !== null && props.now >= Date.parse(deadline.value))
const queued = computed(
  () =>
    (match.value !== null && userId.value !== null && match.value.waiting.includes(userId.value)) ||
    (waiting.value?.friendly?.kind === 'match' && waiting.value.friendly.tournamentId === props.tournament.id),
)
const opponentQueued = computed(
  () => match.value !== null && opponent.value !== null && match.value.waiting.includes(opponent.value.user_id),
)

const state = computed(() => {
  const current = match.value
  if (props.entry.eliminated) {
    return 'eliminated'
  }
  if (current === null || current.outcome !== null) {
    return 'through'
  }
  if (current.game_id !== null) {
    return 'playing'
  }
  if (queued.value) {
    return 'queued'
  }

  return late.value ? 'late' : 'join'
})

/** another game of the player, not ended: conceded before joining */
const otherGame = ref<UserGame | null>(null)
const confirmingConcession = ref(false)
const sending = ref(false)
const error = ref<string | null>(null)
const { sending: leaving, error: leaveError, leave } = useLeaveQueue()

async function readOtherGame(): Promise<void> {
  const token = session.value?.token
  if (!token) {
    return
  }
  try {
    otherGame.value =
      (await fetchSelfGames(token)).find((game) => !game.ended && game.id !== match.value?.game_id) ?? null
  } catch {
    otherGame.value = null
  }
}

onMounted(readOtherGame)
watch(state, (current) => (current === 'join' ? void readOtherGame() : undefined))

async function join(): Promise<void> {
  const current = session.value
  const target = match.value
  if (current === null || target === null || deadline.value === null || sending.value) {
    return
  }
  if (otherGame.value !== null && !confirmingConcession.value) {
    confirmingConcession.value = true

    return
  }
  sending.value = true
  error.value = null
  try {
    if (otherGame.value !== null) {
      await sendMessage(current.token, otherGame.value.id, 'concede_game', {}, otherGame.value.version)
      otherGame.value = null
    }
    await joinQueueWithMatch(current.token, target.id)
    startWaiting(
      current.userId,
      { id: props.entry.deck_id, name: props.entry.deck_name ?? 'Your deck', starter: false },
      {
        kind: 'match',
        opponentName: opponent.value?.name ?? 'your opponent',
        tournamentId: props.tournament.id,
        deadline: deadline.value,
      },
    )
    // matched at once when the opponent waited: the game opens (QueueFollower)
    await checkQueue(current.token, current.userId)
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'The queue cannot be joined. Try again in a moment.'
    await readOtherGame()
  } finally {
    sending.value = false
    confirmingConcession.value = false
    emit('changed')
  }
}

async function leaveQueue(): Promise<void> {
  await leave()
  emit('changed')
}
</script>

<template>
  <section class="match inverted" aria-labelledby="match-title">
    <div class="text">
      <template v-if="state === 'eliminated'">
        <div class="eyebrow">{{ round }}</div>
        <h2 id="match-title">You are out of the tournament</h2>
        <p class="lead">Thank you for playing. Follow the rest of it below.</p>
      </template>
      <template v-else-if="state === 'through'">
        <div class="eyebrow">{{ round }} · you are through</div>
        <h2 id="match-title">Waiting for the other matches</h2>
        <p class="lead">The next round begins as soon as every match of this one has ended: come back here then.</p>
      </template>
      <template v-else>
        <div class="eyebrow">{{ round }} · your match</div>
        <h2 id="match-title">You face {{ opponent?.name }}</h2>
        <p class="lead">
          With {{ entry.deck_name ?? 'your deck' }}.
          <template v-if="state === 'playing'">Your game has begun.</template>
          <template v-else-if="state === 'queued'">You are in the queue: the game opens as soon as they join.</template>
          <template v-else-if="state === 'late'"
            >The time to join is over: the result is recorded in a moment.</template
          >
          <template v-else-if="opponentQueued">{{ opponent?.name }} is already in the queue.</template>
        </p>
      </template>
    </div>

    <div v-if="(state === 'join' || state === 'queued') && deadline" class="timer" role="timer">
      <span class="time">{{ countdownLabel(deadline, now) }}</span>
      <span class="hint">{{
        state === 'queued' ? `left for ${opponent?.name} to join` : 'left to join, else you forfeit'
      }}</span>
    </div>

    <div class="actions">
      <RouterLink
        v-if="state === 'playing' && match?.game_id"
        :to="{ name: 'game', params: { id: match.game_id } }"
        class="btn btn-primary btn-large"
        >Open your game</RouterLink
      >
      <template v-else-if="state === 'join'">
        <p v-if="confirmingConcession" class="confirm">Your other game is still in progress: you concede it to join.</p>
        <button type="button" class="btn btn-primary btn-large" :disabled="sending" @click="join">
          {{
            otherGame === null
              ? 'Join the queue'
              : confirmingConcession
                ? 'Concede and join'
                : 'Concede your game and join'
          }}
        </button>
        <button v-if="confirmingConcession" type="button" class="btn-link" @click="confirmingConcession = false">
          Keep playing it
        </button>
      </template>
      <button
        v-else-if="state === 'queued'"
        type="button"
        class="btn btn-ghost"
        :disabled="leaving"
        @click="leaveQueue"
      >
        Leave the queue
      </button>
      <p v-if="error || leaveError" class="form-error" role="alert">{{ error ?? leaveError }}</p>
    </div>
  </section>
</template>

<style scoped>
.match {
  display: flex;
  align-items: center;
  gap: 32px;
  padding: 26px 32px;
  background: var(--paper);
  border-radius: 2px;
}

.text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
}

.eyebrow {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--seal);
}

h2 {
  font-size: 34px;
  line-height: 1.05;
}

.lead,
.hint,
.confirm {
  font-size: 16px;
  color: var(--ink-2);
}

.hint {
  font-size: 14px;
}

.timer {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.time {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 700;
  line-height: 1;
}

.actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  max-width: 320px;
}

.confirm {
  text-align: right;
}
</style>

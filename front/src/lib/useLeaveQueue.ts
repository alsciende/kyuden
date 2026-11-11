import { ref, type Ref } from 'vue'
import { useRouter } from 'vue-router'

import { found, leave, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'

export interface LeaveQueue {
  sending: Ref<boolean>
  error: Ref<string | null>
  leave(): Promise<void>
}

/**
 * Leaving the queue, wherever the wait shows: the host of an invitation, which leaving cancels, goes back to their
 * invitations; matched meanwhile, the game is found.
 */
export function useLeaveQueue(): LeaveQueue {
  const router = useRouter()
  const sending = ref(false)
  const error = ref<string | null>(null)

  return {
    sending,
    error,
    async leave(): Promise<void> {
      const current = session.value
      if (current === null || sending.value) {
        return
      }
      const hosting = waiting.value?.friendly != null && waiting.value.friendly.kind !== 'host'
      sending.value = true
      error.value = null
      try {
        await leave(current.token, current.userId)
        // matched meanwhile: the game opens instead
        if (hosting && found.value === null) {
          await router.push({ name: 'invite' })
        }
      } catch {
        error.value = 'The queue cannot be left.'
      } finally {
        sending.value = false
      }
    },
  }
}

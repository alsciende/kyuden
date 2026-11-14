import { fetchSelf, updateSelf } from '@/api/client'
import type { Banner, MonId, NoboriCollection, PatternId } from '@/data/nobori'

/**
 * The nobori of the user and what they may choose for it, read and changed through `/api/self`; a mon or a pattern
 * they have not earned is refused by the API.
 */
export interface OwnNobori {
  banner: Banner
  unlocked: NoboriCollection
}

export async function loadOwnNobori(token: string): Promise<OwnNobori> {
  const self = await fetchSelf(token)

  return { banner: self.banner, unlocked: self.unlocked }
}

export async function chooseNobori(token: string, changes: Partial<Banner>): Promise<OwnNobori> {
  const self = await updateSelf(token, { banner: changes })

  return { banner: self.banner, unlocked: self.unlocked }
}

/** whether the user has earned it (the ensō and no pattern from the start) */
export function isEarned(unlocked: NoboriCollection, id: MonId | PatternId): boolean {
  return (unlocked.mons as string[]).includes(id) || (unlocked.patterns as string[]).includes(id)
}

/** how many the user has earned of all there are: "2 of 8 earned" */
export function earnedCount(earned: unknown[], all: unknown[]): string {
  return `${earned.length} of ${all.length} earned`
}

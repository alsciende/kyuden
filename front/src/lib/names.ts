import type { Clan } from '@/data/clans'

export const GIVEN_NAMES = [
  'Takeshi',
  'Aiko',
  'Haruki',
  'Kaede',
  'Renji',
  'Sayuri',
  'Kenshin',
  'Mitsuko',
  'Ryota',
  'Hana',
  'Tadaka',
  'Yumi',
  'Daisuke',
  'Natsumi',
  'Kazuo',
  'Rin',
  'Toshiro',
  'Emi',
  'Masato',
  'Kiyomi',
  'Jiro',
  'Shizue',
  'Hideo',
  'Chiyo',
]

export const MAX_NAME_LENGTH = 64

function pick<T>(items: T[], random: () => number): T {
  return items[Math.floor(random() * items.length)] as T
}

/**
 * A name at court for a samurai of the clan: one of its families, then a given name, as is the custom in Rokugan.
 */
export function drawCourtName(clan: Clan, random: () => number = Math.random): string {
  return `${pick(clan.families, random)} ${pick(GIVEN_NAMES, random)}`
}

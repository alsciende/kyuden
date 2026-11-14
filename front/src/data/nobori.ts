import enso from '@/assets/mons/neutral.svg'
import asanoha from '@/assets/patterns/asanoha.svg'
import dandara from '@/assets/patterns/dandara.svg'
import ichimatsu from '@/assets/patterns/ichimatsu.svg'
import kikko from '@/assets/patterns/kikko.svg'
import koshi from '@/assets/patterns/koshi.svg'
import nami from '@/assets/patterns/nami.svg'
import samekomon from '@/assets/patterns/samekomon.svg'
import seigaiha from '@/assets/patterns/seigaiha.svg'
import tatejima from '@/assets/patterns/tatejima.svg'
import uroko from '@/assets/patterns/uroko.svg'
import yagasuri from '@/assets/patterns/yagasuri.svg'
import { CLANS, type ClanId } from '@/data/clans'

/**
 * The nobori of a player: the vertical banner the other players see, a mon at its top on a disc of paper, over a
 * pattern repeated in the ink of the patterns. It looks the same by day and by night (the colors of `--nobori-*` in
 * `styles/base.css`). The values are those of the enums `Mon` and `BannerPattern` of the API.
 */
export type MonId = 'enso' | ClanId

export type PatternId =
  | 'none'
  | 'plain'
  | 'tatejima'
  | 'dandara'
  | 'ichimatsu'
  | 'koshi'
  | 'samekomon'
  | 'uroko'
  | 'nami'
  | 'kikko'
  | 'asanoha'
  | 'seigaiha'
  | 'yagasuri'

export interface Banner {
  mon: MonId
  pattern: PatternId
}

/** what a user may choose for their nobori: the ensō and no pattern from the start, and what they have earned */
export interface NoboriCollection {
  mons: MonId[]
  patterns: PatternId[]
}

export interface Mon {
  id: MonId
  label: string
  /** the URL of the mon, an SVG used as a mask */
  src: string
  /** its own color, on the paper of its disc: the ink for the ensō, the color of the clan for its mon */
  color: string
  /** the mon of a clan sits in a thick ring that touches it; the ensō is a circle already */
  ringed: boolean
}

export interface Pattern {
  id: PatternId
  name: string
  /** what it shows, in a few words */
  gloss: string
  /** the tile repeated over the nobori (black on transparent, used as a mask), at its own size in pixels; none for no
   * pattern and for the solid ink */
  tile: { src: string; width: number; height: number } | null
}

export const DEFAULT_BANNER: Banner = { mon: 'enso', pattern: 'none' }

export const STARTING_COLLECTION: NoboriCollection = { mons: ['enso'], patterns: ['none'] }

export const MONS: Mon[] = [
  { id: 'enso', label: 'Ensō', src: enso, color: '#1f1b16', ringed: false },
  ...CLANS.map((clan): Mon => ({ id: clan.id, label: clan.name, src: clan.mon, color: clan.color, ringed: true })),
]

export const PATTERNS: Pattern[] = [
  { id: 'none', name: 'None', gloss: 'No pattern', tile: null },
  { id: 'plain', name: 'Plain', gloss: 'Solid', tile: null },
  { id: 'tatejima', name: 'Tatejima', gloss: 'Vertical stripes', tile: { src: tatejima, width: 12, height: 12 } },
  { id: 'dandara', name: 'Dandara', gloss: 'Broad bands', tile: { src: dandara, width: 10, height: 56 } },
  { id: 'ichimatsu', name: 'Ichimatsu', gloss: 'Checks', tile: { src: ichimatsu, width: 20, height: 20 } },
  { id: 'koshi', name: 'Kōshi', gloss: 'Lattice', tile: { src: koshi, width: 20, height: 20 } },
  { id: 'samekomon', name: 'Same komon', gloss: 'Fine dots', tile: { src: samekomon, width: 12, height: 12 } },
  { id: 'uroko', name: 'Uroko', gloss: 'Scales', tile: { src: uroko, width: 20, height: 20 } },
  { id: 'nami', name: 'Nami', gloss: 'Waves', tile: { src: nami, width: 24, height: 12 } },
  { id: 'kikko', name: 'Kikkō', gloss: 'Tortoise shell', tile: { src: kikko, width: 17.32, height: 30 } },
  { id: 'asanoha', name: 'Asanoha', gloss: 'Hemp leaf', tile: { src: asanoha, width: 20, height: 34.64 } },
  { id: 'seigaiha', name: 'Seigaiha', gloss: 'Waves of the sea', tile: { src: seigaiha, width: 40, height: 20 } },
  { id: 'yagasuri', name: 'Yagasuri', gloss: 'Arrow feathers', tile: { src: yagasuri, width: 40, height: 40 } },
]

/** a value the client does not know (a newer API) falls back on the ensō */
export function monOf(id: string | null | undefined): Mon {
  return MONS.find((mon) => mon.id === id) ?? MONS[0]!
}

/** a value the client does not know falls back on no pattern */
export function patternOf(id: string | null | undefined): Pattern {
  return PATTERNS.find((pattern) => pattern.id === id) ?? PATTERNS[0]!
}

/** the accessible name of a nobori: "Crab mon on a Seigaiha nobori" */
export function noboriLabel(banner: Banner): string {
  const mon = monOf(banner.mon).label
  const pattern = patternOf(banner.pattern)

  return pattern.id === 'none' ? `${mon} mon on a nobori without pattern` : `${mon} mon on a ${pattern.name} nobori`
}

/**
 * The sizes of a nobori of a given width, in pixels: the mon on its disc, its ring, and the scale of the tiles (those
 * of a nobori of 100px at their own size, never smaller than 60%), so that the nobori of the popover and that of the
 * announcement look alike.
 */
export function noboriSizes(width: number): {
  disc: number
  mon: number
  ring: number
  ringed: number
  top: number
  scale: number
} {
  const mon = Math.round(width * 0.69)
  const ring = Math.max(2, Math.round(mon * 0.08))

  return {
    disc: Math.round(mon * 1.24),
    mon,
    ring,
    ringed: mon - 2 * ring + 2,
    top: Math.round(width * 0.1),
    scale: Math.max(0.6, width / 100),
  }
}

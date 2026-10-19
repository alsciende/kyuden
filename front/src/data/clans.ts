import crab from '@/assets/mons/crab.svg'
import crane from '@/assets/mons/crane.svg'
import dragon from '@/assets/mons/dragon.svg'
import lion from '@/assets/mons/lion.svg'
import phoenix from '@/assets/mons/phoenix.svg'
import scorpion from '@/assets/mons/scorpion.svg'
import unicorn from '@/assets/mons/unicorn.svg'

export type ClanId = 'crab' | 'crane' | 'dragon' | 'lion' | 'phoenix' | 'scorpion' | 'unicorn'

export interface Clan {
  id: ClanId
  name: string
  kanji: string
  motto: string
  /** how the starter deck plays, in a sentence or two */
  style: string
  /** the families of the clan, from which the names at court are drawn */
  families: string[]
  /** the color of the clan on paper, for its mon */
  color: string
  /** URL of the mon (an SVG used as a mask) */
  mon: string
  /** the starter deck of the clan, a deck of the engine without owner */
  deckId: string
  strongholdCode: string
  championCode: string
}

export const CLANS: Clan[] = [
  {
    id: 'crab',
    name: 'Crab',
    kanji: '蟹',
    motto: 'The Wall must hold.',
    style:
      'Unbreakable defenders and a punishing military game. Let the enemy break on your provinces, then strike back.',
    families: ['Hida', 'Hiruma', 'Kaiu', 'Yasuki'],
    color: '#3e5c84',
    mon: crab,
    deckId: 'crab-starter-deck',
    strongholdCode: '01-shiro-nishiyama',
    championCode: '01-hida-kisada',
  },
  {
    id: 'crane',
    name: 'Crane',
    kanji: '鶴',
    motto: 'Grace in court, steel in the duel.',
    style: 'Duelists and courtiers. Win through honor, political strength and the favor of the Emperor.',
    families: ['Doji', 'Kakita', 'Daidoji', 'Asahina'],
    color: '#4f86b8',
    mon: crane,
    deckId: 'crane-starter-deck',
    strongholdCode: '01-shizuka-toshi',
    championCode: '01-doji-hotaru',
  },
  {
    id: 'dragon',
    name: 'Dragon',
    kanji: '龍',
    motto: 'Wisdom from the mountain.',
    style: 'Monks and two-sword duelists. Few characters, well equipped, who can strike more than once.',
    families: ['Togashi', 'Mirumoto', 'Kitsuki', 'Agasha'],
    color: '#2f7a5c',
    mon: dragon,
    deckId: 'dragon-starter-deck',
    strongholdCode: '01-mountain-s-anvil-castle',
    championCode: '01-togashi-yokuni',
  },
  {
    id: 'lion',
    name: 'Lion',
    kanji: '獅子',
    motto: 'The Emperor’s right hand.',
    style: 'Overwhelming military force. Fill the battlefield and break provinces through sheer numbers.',
    families: ['Akodo', 'Matsu', 'Ikoma', 'Kitsu'],
    color: '#a87a22',
    mon: lion,
    deckId: 'lion-starter-deck',
    strongholdCode: '01-yojin-no-shiro',
    championCode: '01-akodo-toturi',
  },
  {
    id: 'phoenix',
    name: 'Phoenix',
    kanji: '鳳凰',
    motto: 'Masters of the elements.',
    style: 'Shugenja who bend the rings to their will. Claim the right ring and its power is yours.',
    families: ['Isawa', 'Shiba', 'Asako'],
    color: '#c0652b',
    mon: phoenix,
    deckId: 'phoenix-starter-deck',
    strongholdCode: '01-isawa-mori-seido',
    championCode: '01-shiba-tsukune',
  },
  {
    id: 'scorpion',
    name: 'Scorpion',
    kanji: '蠍',
    motto: 'The Emperor’s underhand.',
    style: 'Dishonor and deceit. Drain your opponent’s honor and turn their own strength against them.',
    families: ['Bayushi', 'Shosuro', 'Soshi', 'Yogo'],
    color: '#a52a2f',
    mon: scorpion,
    deckId: 'scorpion-starter-deck',
    strongholdCode: '01-city-of-the-open-hand',
    championCode: '01-bayushi-shoju',
  },
  {
    id: 'unicorn',
    name: 'Unicorn',
    kanji: '一角獣',
    motto: 'Riders from beyond the Empire.',
    style: 'Speed and raw power. Ride where the enemy is weakest and strike before they can answer.',
    families: ['Shinjo', 'Utaku', 'Ide', 'Iuchi', 'Moto'],
    color: '#6e3f8e',
    mon: unicorn,
    deckId: 'unicorn-starter-deck',
    strongholdCode: '01-golden-plains-outpost',
    championCode: '01-shinjo-altansarnai',
  },
]

export function findClan(id: string | null | undefined): Clan | undefined {
  return CLANS.find((clan) => clan.id === id)
}

/**
 * The clan of a starter deck of the engine (`lion-starter-deck`).
 */
export function findClanByDeck(deckId: string): Clan | undefined {
  return CLANS.find((clan) => clan.deckId === deckId)
}

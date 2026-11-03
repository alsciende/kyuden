/**
 * The house bot of the platform (`HOUSE_BOT_ID`), which the players face to train (`POST /api/bot-games`): its name at
 * court, as the engine names it, and what it is.
 */
export const HOUSE_BOT = {
  name: 'Kyūden no Karō',
  /** what it is, after its name: "Kyūden no Karō, the steward of the palace" */
  title: 'the steward of the palace',
  /** the same, alone */
  role: 'The steward of the palace',
} as const

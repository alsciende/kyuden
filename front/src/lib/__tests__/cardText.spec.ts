import { describe, expect, it } from 'vitest'

import { emphasized, parseCardText } from '@/lib/cardText'
import { CONFLICT_ICONS } from '@/lib/icons'

describe('parseCardText', () => {
  it('splits the paragraphs and reads the timings of their abilities', () => {
    const paragraphs = parseCardText(
      'If you control Doji Hotaru, discard her.<br><b>Action:</b> During a [conflict-military] conflict – bow that character.',
    )

    expect(paragraphs.map((paragraph) => paragraph.timings)).toEqual([[], ['action']])
    expect(paragraphs[1]!.segments).toEqual([
      { kind: 'text', text: 'Action:', bold: true, italic: false, timing: true },
      { kind: 'text', text: ' During a ', bold: false, italic: false, timing: false },
      { kind: 'icon', glyph: CONFLICT_ICONS.military, label: 'military' },
      { kind: 'text', text: ' conflict – bow that character.', bold: false, italic: false, timing: false },
    ])
  })

  it('reads forced abilities, those granted in quotes, italics and clans', () => {
    const [granted, forced] = parseCardText(
      'A [clan-crane] character gains "<b>Action: </b>switch the conflict type." <i>(Reminder.)</i>\n<b>Forced Reaction:</b> After…',
    )

    expect(granted!.timings).toEqual(['action'])
    expect(granted!.segments[1]).toMatchObject({ kind: 'clan', label: 'Crane' })
    expect(granted!.segments.at(-1)).toMatchObject({ text: '(Reminder.)', italic: true })
    expect(forced!.timings).toEqual(['reaction'])
  })

  it('reads a card without text, and drops the markup it does not know', () => {
    expect(parseCardText(null)).toEqual([])
    expect(
      parseCardText('<u>Pride</u>. [unknown-symbol]')[0]!.segments.map((segment) => 'text' in segment && segment.text),
    ).toEqual(['Pride. ', '[unknown-symbol]'])
  })
})

describe('emphasized', () => {
  it('emphasizes the paragraphs of the timing, or all of them when none matches', () => {
    const paragraphs = parseCardText('Pride.<br><b>Action:</b> Bow.<br><b>Reaction:</b> Ready.')

    expect(emphasized(paragraphs, 'reaction')).toEqual([false, false, true])
    expect(emphasized(paragraphs, 'interrupt')).toEqual([true, true, true])
  })
})

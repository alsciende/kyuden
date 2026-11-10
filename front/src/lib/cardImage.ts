import { ref } from 'vue'

/**
 * The card whose image is shown alone over the page, read-only (`CardImageViewer.vue`): opened by a click on the name
 * of a card in a text (`CardName.vue`), the log among others, where a touch screen cannot hover it.
 */
export const viewedCode = ref<string | null>(null)

export function viewCardImage(code: string): void {
  viewedCode.value = code
}

export function closeCardImage(): void {
  viewedCode.value = null
}

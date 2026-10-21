import { ref } from 'vue'

/**
 * Whether the page around the view (header and footer included) is shown in reverse, light ink on a dark ground: the
 * view that asks for it resets it when it is left.
 */
export const inverted = ref(false)

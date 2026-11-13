import '@fontsource/eb-garamond/400.css'
import '@fontsource/eb-garamond/700.css'
// the kanji, which EB Garamond does not draw (loaded only for them: unicode ranges)
import '@fontsource/shippori-mincho/400.css'
import '@fontsource/shippori-mincho/700.css'
import '@fontsource/barlow/400.css'
import '@fontsource/barlow/500.css'
import '@fontsource/barlow/700.css'
import './styles/base.css'

import { createApp } from 'vue'

import App from './App.vue'
import { followPhoneViewport } from './lib/phoneViewport'
import { followRefusedTokens } from './lib/session'
import { followTheme } from './lib/theme'
import { router } from './router'

// a phone gets the layout of a tablet, scaled down: before the first render
followPhoneViewport()
followTheme()
// an account whose token is refused signs in again, then comes back to the page it was on
followRefusedTokens(() => {
  const current = router.currentRoute.value
  if (current.name !== 'login') {
    void router.push({ name: 'login', query: current.name === 'landing' ? {} : { next: current.fullPath } })
  }
})
createApp(App).use(router).mount('#app')

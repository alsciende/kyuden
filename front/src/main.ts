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
import { router } from './router'

createApp(App).use(router).mount('#app')

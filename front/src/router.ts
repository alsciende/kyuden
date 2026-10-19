import { createRouter, createWebHistory } from 'vue-router'

import { findClan } from '@/data/clans'
import { newcomer } from '@/lib/newcomer'
import GameView from '@/views/GameView.vue'
import LandingView from '@/views/LandingView.vue'
import NameView from '@/views/NameView.vue'
import OpponentView from '@/views/OpponentView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: LandingView, meta: { title: 'Kyūden' } },
    {
      path: '/name',
      name: 'name',
      component: NameView,
      meta: { title: 'Your name at court · Kyūden' },
      beforeEnter: () => (findClan(newcomer.clanId) ? true : { name: 'landing' }),
    },
    {
      path: '/opponent',
      name: 'opponent',
      component: OpponentView,
      meta: { title: 'Your opponent · Kyūden' },
      beforeEnter: () => (findClan(newcomer.clanId) && newcomer.name ? true : { name: 'landing' }),
    },
    { path: '/games/:id', name: 'game', component: GameView, meta: { title: 'The table is set · Kyūden' } },
    { path: '/:path(.*)*', redirect: { name: 'landing' } },
  ],
})

router.afterEach((to) => {
  document.title = typeof to.meta.title === 'string' ? to.meta.title : 'Kyūden'
})

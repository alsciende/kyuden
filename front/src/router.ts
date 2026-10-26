import { createRouter, createWebHistory } from 'vue-router'

import { findClan } from '@/data/clans'
import { newcomer } from '@/lib/newcomer'
import { session } from '@/lib/session'
import AccountView from '@/views/AccountView.vue'
import GameView from '@/views/GameView.vue'
import LandingView from '@/views/LandingView.vue'
import LoginView from '@/views/LoginView.vue'
import NameView from '@/views/NameView.vue'
import OAuthCallbackView from '@/views/OAuthCallbackView.vue'
import OpponentView from '@/views/OpponentView.vue'
import PrivacyView from '@/views/PrivacyView.vue'
import QueueView from '@/views/QueueView.vue'
import SignupView from '@/views/SignupView.vue'
import TermsView from '@/views/TermsView.vue'

const hasAccount = () => session.value !== null && session.value.username !== null

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
    { path: '/login', name: 'login', component: LoginView, meta: { title: 'Sign in · Kyūden' } },
    {
      path: '/signup',
      name: 'signup',
      component: SignupView,
      meta: { title: 'Create your account · Kyūden' },
      beforeEnter: () => (hasAccount() ? { name: 'account' } : true),
    },
    {
      path: '/account',
      name: 'account',
      component: AccountView,
      meta: { title: 'Your account · Kyūden' },
      beforeEnter: () => (hasAccount() ? true : { name: 'login' }),
    },
    {
      path: '/queue',
      name: 'queue',
      component: QueueView,
      meta: { title: 'Find an opponent · Kyūden' },
      beforeEnter: () => (hasAccount() ? true : { name: 'login' }),
    },
    // its URL is given to Google and Discord, which publish it on their consent screens
    { path: '/privacy', name: 'privacy', component: PrivacyView, meta: { title: 'Privacy · Kyūden' } },
    // its URL is given to Google and Discord too
    {
      path: '/terms-and-conditions',
      name: 'terms',
      component: TermsView,
      meta: { title: 'Terms and conditions · Kyūden' },
    },
    {
      // the redirect URI registered at each provider
      path: '/oauth/:provider/callback',
      name: 'oauth-callback',
      component: OAuthCallbackView,
      meta: { title: 'Back to court · Kyūden' },
    },
    { path: '/games/:id', name: 'game', component: GameView, meta: { title: 'Game · Kyūden', fullscreen: true } },
    { path: '/:path(.*)*', redirect: { name: 'landing' } },
  ],
})

router.afterEach((to) => {
  document.title = typeof to.meta.title === 'string' ? to.meta.title : 'Kyūden'
})

<script setup lang="ts">
import PlayerNobori from '@/components/PlayerNobori.vue'
import type { Announcement } from '@/lib/matchAnnouncement'

/**
 * The match announced, like a fight about to begin, before the game opens (`QueueFollower`): the nobori of the user
 * sweeps in from the left, that of their opponent from the right, a vermilion slash between them and 対 over it, then
 * "Opponent found", the time left drawn below (`duration`). The scene stays until it is `leaving`: it fades out over the
 * table drawn under it. Without motion
 * (prefers-reduced-motion), it shows at once.
 */
defineProps<{ announcement: Announcement; duration: number; leaving?: boolean }>()
</script>

<template>
  <div class="scene" :class="{ leaving }" role="status" :style="{ '--duration': `${duration}ms` }">
    <div class="flash" aria-hidden="true" />

    <div class="side left">
      <div class="pole">
        <span class="bar" aria-hidden="true" />
        <PlayerNobori :banner="announcement.you.banner" :width="160" class="nobori" />
      </div>
      <div class="who rise">
        <div class="name">{{ announcement.you.name }}</div>
        <div class="caption">You{{ announcement.you.clanName ? ` · ${announcement.you.clanName}` : '' }}</div>
      </div>
    </div>

    <div class="clash" aria-hidden="true">
      <span class="slash" />
      <span class="kanji">対</span>
    </div>

    <div class="side right">
      <div class="who rise">
        <div class="name">{{ announcement.opponent.name }}</div>
        <div v-if="announcement.opponent.clanName" class="caption">{{ announcement.opponent.clanName }}</div>
      </div>
      <div class="pole">
        <span class="bar" aria-hidden="true" />
        <PlayerNobori :banner="announcement.opponent.banner" :width="160" class="nobori" />
      </div>
    </div>

    <div class="footer rise2">
      <h1>Opponent found · Your game opens</h1>
      <div class="timer" aria-hidden="true"><span /></div>
    </div>
  </div>
</template>

<style scoped>
/* dark whatever the theme, its own colors: the stage of the fight */
.scene {
  position: fixed;
  inset: 0;
  z-index: 1000;
  overflow: hidden;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  grid-template-rows: 1fr auto;
  align-items: start;
  padding: 7vh 4vw 6vh;
  box-sizing: border-box;
  background: #0d0b09;
  color: #f4eee2;
}

.scene.leaving {
  animation: leave 0.25s ease-in both;
}

.flash {
  position: absolute;
  inset: 0;
  background: #f4eee2;
  pointer-events: none;
  animation: flash 0.7s ease-out 0.6s both;
}

.side {
  display: flex;
  gap: 28px;
  align-items: flex-start;
}

.left {
  justify-content: flex-end;
  animation: in-left 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.right {
  justify-content: flex-start;
  animation: in-right 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.pole {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.bar {
  width: 190px;
  height: 6px;
  border-radius: 3px;
  background: #a5814a;
}

.nobori {
  height: min(640px, 72vh);
  box-shadow:
    0 0 0 1px rgb(244 238 226 / 0.12),
    0 30px 80px rgb(0 0 0 / 0.6);
}

.who {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 240px;
  padding-top: 56px;
}

.right .who {
  align-items: flex-end;
  text-align: right;
}

.name {
  font-family: var(--font-display);
  font-size: 42px;
  font-weight: 600;
  line-height: 1;
  overflow-wrap: anywhere;
}

.caption {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #b3a894;
}

.clash {
  position: relative;
  width: 200px;
  height: min(640px, 72vh);
  display: flex;
  align-items: center;
  justify-content: center;
}

.slash {
  position: absolute;
  left: 50%;
  top: 0;
  bottom: 0;
  width: 3px;
  margin-left: -1.5px;
  background: #e2553f;
  box-shadow: 0 0 24px rgb(226 85 63 / 0.8);
  transform-origin: center;
  animation: slash 0.5s cubic-bezier(0.6, 0, 0.2, 1) 0.55s both;
}

.kanji {
  position: relative;
  font-family: var(--font-display);
  font-size: 150px;
  font-weight: 700;
  line-height: 1;
  text-shadow:
    0 0 40px #0d0b09,
    0 0 18px #0d0b09;
  animation: kanji-in 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) 0.65s both;
}

.footer {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

h1 {
  font-family: var(--font-ui);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #cfc5b3;
}

.timer {
  width: 240px;
  height: 2px;
  background: rgb(244 238 226 / 0.15);
  overflow: hidden;
}

.timer span {
  display: block;
  height: 100%;
  background: #e2553f;
  transform-origin: left;
  animation: timer var(--duration) linear both;
}

.rise {
  animation: rise 0.45s ease-out 0.85s both;
}

.rise2 {
  animation: rise 0.45s ease-out 1.05s both;
}

.nobori :deep(.disc) {
  animation: mon-in 0.45s cubic-bezier(0.2, 0.8, 0.2, 1) 0.45s both;
}

@keyframes in-left {
  0% {
    transform: translateX(-140%) skewX(-8deg);
    opacity: 0;
  }
  70% {
    transform: translateX(4%) skewX(1deg);
    opacity: 1;
  }
  100% {
    transform: none;
    opacity: 1;
  }
}

@keyframes in-right {
  0% {
    transform: translateX(140%) skewX(8deg);
    opacity: 0;
  }
  70% {
    transform: translateX(-4%) skewX(-1deg);
    opacity: 1;
  }
  100% {
    transform: none;
    opacity: 1;
  }
}

@keyframes mon-in {
  0% {
    transform: scale(1.5);
    opacity: 0;
  }
  100% {
    transform: none;
    opacity: 1;
  }
}

@keyframes slash {
  0% {
    transform: scaleY(0);
    opacity: 1;
  }
  40% {
    transform: scaleY(1);
    opacity: 1;
  }
  100% {
    transform: scaleY(1);
    opacity: 0.55;
  }
}

@keyframes flash {
  0% {
    opacity: 0;
  }
  15% {
    opacity: 0.35;
  }
  100% {
    opacity: 0;
  }
}

@keyframes kanji-in {
  0% {
    transform: scale(1.8);
    opacity: 0;
  }
  100% {
    transform: none;
    opacity: 1;
  }
}

@keyframes rise {
  0% {
    transform: translateY(14px);
    opacity: 0;
  }
  100% {
    transform: none;
    opacity: 1;
  }
}

@keyframes timer {
  0% {
    transform: scaleX(0);
  }
  100% {
    transform: scaleX(1);
  }
}

@keyframes leave {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .left,
  .right,
  .flash,
  .slash,
  .kanji,
  .rise,
  .rise2,
  .nobori :deep(.disc) {
    animation-duration: 0.01s;
    animation-delay: 0s;
  }
}
</style>

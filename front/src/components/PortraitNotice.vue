<!--
  Over every page of a phone held upright (`data-phone="portrait"` on the root, set by the script of index.html): the
  layout of a tablet, scaled down, is only playable in landscape. Sized in vw: the page is scaled down to the width of
  the phone. The phone turning is Lucide's "smartphone" (lucide.dev, ISC license).
-->
<template>
  <div class="portrait-notice" role="alert">
    <svg
      class="phone"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.5"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
      <path d="M12 18h.01" />
    </svg>
    <p class="title">Turn your phone</p>
    <p class="text">Kyūden is played in landscape.</p>
  </div>
</template>

<style scoped>
.portrait-notice {
  display: none;
}

:root[data-phone='portrait'] .portrait-notice {
  position: fixed;
  inset: 0;
  /* above the pages, under the windows of the top layer */
  z-index: 1000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4vw;
  padding: 0 10vw;
  background: var(--paper);
  color: var(--ink);
  text-align: center;
}

.phone {
  width: 18vw;
  height: 18vw;
  margin-bottom: 4vw;
  animation: turn 2.4s ease-in-out infinite;
}

@keyframes turn {
  0%,
  25% {
    transform: rotate(0);
  }

  50%,
  75% {
    transform: rotate(-90deg);
  }

  100% {
    transform: rotate(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .phone {
    animation: none;
    transform: rotate(-90deg);
  }
}

.title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 8vw;
  font-weight: 500;
}

.text {
  margin: 0;
  font-size: 4.5vw;
  color: var(--ink-2);
}
</style>

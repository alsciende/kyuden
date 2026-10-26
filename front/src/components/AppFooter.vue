<template>
  <footer class="footer">
    <span>Kyūden is a fan-made project, not affiliated with Fantasy Flight Games.</span>
    <nav class="links">
      <RouterLink :to="{ name: 'terms' }">Terms and conditions</RouterLink>
      <RouterLink :to="{ name: 'privacy' }">Privacy</RouterLink>
    </nav>
  </footer>
</template>

<style scoped>
.footer {
  padding: 16px 48px;
  border-top: 1px solid var(--line);
  font-size: 13px;
  color: var(--ink-3);
  display: flex;
  justify-content: space-between;
  gap: 24px;
}

.links {
  display: flex;
  gap: 24px;
}

.footer a {
  color: var(--ink-3);
  text-underline-offset: 3px;
}

.footer a:hover {
  color: var(--seal);
}
</style>

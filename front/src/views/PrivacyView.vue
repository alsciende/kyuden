<template>
  <article class="privacy screen">
    <header class="title">
      <div class="eyebrow">Privacy</div>
      <h1>What Kyūden keeps, and why</h1>
      <p class="lead">
        Kyūden asks for no personal data: no email address, no real name, no birth date. It keeps what you play with,
        and nothing more. Nothing is sold, shared or used for advertising.
      </p>
      <p class="updated">Last updated on 26 October 2026.</p>
    </header>

    <section>
      <h2>Your account</h2>
      <ul>
        <li>
          Your <strong>username</strong>, to sign in, and your <strong>password</strong>, stored only as a hash: nobody
          can read it, not even us.
        </li>
        <li>
          Your <strong>name at court</strong>, the name the other players see, which you choose and change at any time.
        </li>
        <li>Your <strong>preferred clan</strong> and your <strong>website settings</strong>.</li>
        <li>The <strong>decks</strong> you import, which only you see.</li>
        <li>
          The <strong>games</strong> you play: every move, so that a game can be followed and resumed. Your opponent
          sees your name at court, your clan and your moves, never your hand nor your secret choices.
        </li>
      </ul>
      <p>A guest, who plays without an account, has only a name at court and their games.</p>
      <p>
        You can delete your account from its page, at any time. Everything above is then deleted, and your bots with it,
        but your games: they belong to your opponents too, and stay, under the name “A former player”. Your games in
        progress are conceded.
      </p>
      <p>
        When you sign in, your browser receives a random key that keeps you signed in. Kyūden keeps only a fingerprint
        of it, from which the key cannot be found again: whoever read our database could not sign in as you.
      </p>
    </section>

    <section>
      <h2>Signing in with Google or Discord</h2>
      <p>
        Once your account is linked to Google or Discord, you can sign in with it. Kyūden then keeps a single thing: the
        <strong>identifier of your account at the provider</strong> (an opaque number), and the date you linked it, to
        recognize you when you come back.
      </p>
      <ul>
        <li>
          Kyūden asks the provider for nothing else: Google only for <code>openid</code>, Discord only for
          <code>identify</code>. No email address, name, picture nor list of friends is asked for or kept.
        </li>
        <li>
          What the provider sends to sign you in is used once, by the server, and never kept nor sent to your browser.
        </li>
        <li>When you unlink the provider from your account, its identifier is deleted.</li>
      </ul>
    </section>

    <section>
      <h2>Your browser</h2>
      <p>
        Kyūden sets no cookie and uses no analytics, advertising nor tracking of any kind. Your browser keeps your
        session, so that you stay signed in (until you sign out), and, for the time of a tab, the clan and name chosen
        before your first game and the state of a sign-in with a provider.
      </p>
    </section>

    <section>
      <h2>Kept for a moment</h2>
      <ul>
        <li>
          Your <strong>IP address</strong>, for a minute, counts the attempts to sign in, so that nobody can try every
          password.
        </li>
        <li>A sign-in with a provider is remembered for ten minutes at most, until it is completed.</li>
        <li>The server writes its errors in a log, to fix them.</li>
      </ul>
    </section>

    <section>
      <h2>Elsewhere</h2>
      <p>
        The images of the cards are loaded from the servers that host them (those of Fantasy Flight Games, Imgur), which
        see the address of your browser as any site does. Google and Discord apply their own privacy policies when you
        sign in with them.
      </p>
    </section>

    <section>
      <h2>Contact</h2>
      <p>To ask a question about your data, write to <a href="mailto:alsciende@kyuden.app">alsciende@kyuden.app</a>.</p>
    </section>
  </article>
</template>

<style scoped>
.privacy {
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding: 56px 48px 72px;
  max-width: 760px;
  width: 100%;
  margin: 0 auto;
}

.title {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

h1 {
  font-size: 56px;
  line-height: 1.05;
}

.lead {
  font-size: 19px;
  line-height: 1.6;
  color: var(--ink-2);
}

.updated {
  font-size: 14px;
  color: var(--ink-3);
}

section {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 32px;
  border-top: 1px solid var(--line);
}

h2 {
  font-size: 30px;
  line-height: 1.15;
}

section p,
li {
  font-size: 16px;
  line-height: 1.65;
  color: var(--ink-2);
}

ul {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding-left: 20px;
}

strong {
  font-weight: 600;
  color: var(--ink);
}

section a {
  color: var(--ink);
  text-underline-offset: 3px;
}

section a:hover {
  color: var(--seal);
}

code {
  font-size: 14px;
  padding: 1px 5px;
  border-radius: 3px;
  background: var(--paper-2);
}
</style>

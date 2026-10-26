<template>
  <article class="terms screen">
    <header class="title">
      <div class="eyebrow">Terms and conditions</div>
      <h1>The rules of the court</h1>
      <p class="lead">
        Kyūden is a free website to play Legend of the Five Rings, made by fans for fans. By playing on it, as a guest
        or with an account, you accept these terms.
      </p>
      <p class="updated">Last updated on 26 October 2026.</p>
    </header>

    <section>
      <h2>A fan-made website</h2>
      <p>
        Legend of the Five Rings, its cards, their texts and images belong to Fantasy Flight Games. Kyūden is not
        affiliated with nor endorsed by Fantasy Flight Games, and it sells nothing: it costs nothing to play, and no
        part of the game is behind a payment.
      </p>
    </section>

    <section>
      <h2>Your account</h2>
      <ul>
        <li>
          You keep your password to yourself, and you are responsible for what is done with your account. If you think
          someone else uses it, change your password and sign out.
        </li>
        <li>
          Your <strong>name at court</strong> is seen by every player you meet: it must not insult, impersonate or
          deceive anyone, nor carry hateful or obscene words.
        </li>
        <li>A guest who loses the key their browser keeps loses their guest account, and their games with it.</li>
      </ul>
    </section>

    <section>
      <h2>Fair play</h2>
      <p>
        Kyūden acts as the board: it follows the steps of the game, but it does not apply the cards. The players apply
        them, honestly, as they would at a table.
      </p>
      <ul>
        <li>Apply the effects of the cards as they are written, and declare your totals truthfully.</li>
        <li>Do not act for your opponent, nor take advantage of a mistake of the website.</li>
        <li>
          Do not try to see what is hidden from you: the hand of your opponent, their decks, their secret choices.
        </li>
        <li>Do not abuse other players, in your name at court or elsewhere.</li>
      </ul>
    </section>

    <section>
      <h2>Bots and artificial intelligence</h2>
      <p>
        Programs are welcome to play, as long as everyone knows who is playing. A program plays only with a
        <strong>bot account</strong>: a human creates it and remains responsible for it, and its opponents see that it
        is a bot.
      </p>
      <ul>
        <li>A human account is played by a human, never by a program.</li>
        <li>
          While you play against another human, you make your own decisions: no artificial intelligence or other program
          may read the game for you or suggest your moves. Reading the rules, the cards and the rulings is of course
          fine.
        </li>
        <li>Against the house bot, you may use the help you want.</li>
        <li>
          A program uses the API as it is documented, without flooding the website with requests nor trying to reach
          what its account cannot.
        </li>
      </ul>
    </section>

    <section>
      <h2>If the rules are broken</h2>
      <p>
        A game in which a player broke these terms may be ended, and an account that breaks them may be renamed,
        suspended or deleted, its bots and keys with it.
      </p>
    </section>

    <section>
      <h2>No guarantee</h2>
      <p>
        Kyūden is offered as it is, by volunteers, without any guarantee: the website may be unavailable, a game may be
        lost, the rules of the game may be wrongly enforced, and the website may change or close at any time. It keeps
        no promise of any kind about your games, your decks or your account.
      </p>
    </section>

    <section>
      <h2>Changes</h2>
      <p>
        These terms may change as the website does: the date above tells when they last did. Playing after a change
        means accepting the new terms. What Kyūden keeps about you is told in the
        <RouterLink :to="{ name: 'privacy' }">privacy policy</RouterLink>.
      </p>
    </section>

    <section>
      <h2>Contact</h2>
      <p>
        To report a player or a bot, to contest a decision about your account or to ask a question about these terms,
        write to <a href="mailto:alsciende@kyuden.app">alsciende@kyuden.app</a>.
      </p>
    </section>
  </article>
</template>

<style scoped>
.terms {
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
</style>

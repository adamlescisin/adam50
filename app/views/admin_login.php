<?php $pageTitle = 'Režisérská kabina'; $bodyClass = 'page-landing'; require __DIR__ . '/_head.php'; ?>
<main class="landing">
  <div class="landing__fifty" aria-hidden="true">50</div>
  <p class="eyebrow">Režisérská kabina</p>
  <h1 class="display">Administrace</h1>
  <?php if (!empty($fail)): ?><p class="alert">Klíč nesedí.</p><?php endif; ?>
  <form class="code-form" method="post" action="<?= e($BASE) ?>/admin">
    <label class="sr-only" for="k">Admin klíč</label>
    <input id="k" name="k" type="password" autocomplete="current-password" placeholder="Admin klíč" required autofocus>
    <button class="btn btn--gold" type="submit">Vstoupit</button>
  </form>
</main>
</body>
</html>

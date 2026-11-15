<?php $pageTitle = cfg('site_title'); $bodyClass = 'page-landing'; require __DIR__ . '/_head.php'; ?>
<main class="landing">
  <div class="landing__fifty" aria-hidden="true">50</div>
  <p class="eyebrow"><?= (int)cfg('birth_year') ?> → <?= (int)cfg('birth_year') + 50 ?></p>
  <h1 class="display"><?= e(cfg('site_title')) ?></h1>
  <?php if (!empty($notice)): ?>
    <p class="lead"><?= e($notice) ?></p>
  <?php else: ?>
    <p class="lead">Tahle stránka je jen pro pozvané. Otevři osobní odkaz z pozvánky,<br class="hide-sm"> nebo zadej svůj kód.</p>
  <?php endif; ?>
  <?php if (isset($_GET['neplatny'])): ?><p class="alert">Tenhle kód neznáme. Zkontroluj ho, prosím, nebo se ozvi Adamovi.</p><?php endif; ?>
  <form class="code-form" method="post" action="<?= e($BASE) ?>/prihlasit">
    <label class="sr-only" for="kod">Kód z pozvánky</label>
    <input id="kod" name="kod" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="např. k7m2xq9pza" required>
    <button class="btn btn--gold" type="submit">Vstoupit</button>
  </form>
</main>
</body>
</html>

<?php $pageTitle = 'Režisérská kabina'; $bodyClass = 'page-landing'; require __DIR__ . '/_head.php'; ?>
<main class="landing">
  <div class="landing__fifty" aria-hidden="true">50</div>
  <p class="eyebrow">Režisérská kabina</p>
  <h1 class="display">Administrace</h1>
  <?php if (!empty($fail)): ?><p class="alert">Klíč nesedí.</p><?php endif; ?>
  <?php $src = cfg('__source'); $k = (string)cfg('admin_key'); ?>
  <?php if (str_contains((string)$src, 'example')): ?>
    <p class="alert">Nenašel jsem soukromý config – běží ukázkové nastavení. Očekávám ho přesně tady:<br><code><?= e(dirname(__DIR__, 3) . '/adam50-private/config.php') ?></code>
    <br><small class="muted">DOCUMENT_ROOT: <?= e($_SERVER['DOCUMENT_ROOT'] ?? '?') ?> · HOME: <?= e((string)getenv('HOME')) ?></small>
    <?php $ob = ini_get('open_basedir'); if ($ob): ?><br><small class="muted">open_basedir: <?= e($ob) ?></small><?php endif; ?></p>
  <?php elseif ($k === '' || $k === 'ZMEN-MNE'): ?>
    <p class="alert">Config (<?= e($src) ?>) je načtený, ale admin klíč je prázdný nebo výchozí.</p>
  <?php elseif (!empty($fail)): ?>
    <p class="hint">Config se načítá z <code><?= e($src) ?></code> (klíč má <?= mb_strlen($k) ?> znaků).</p>
  <?php endif; ?>
  <form class="code-form" method="post" action="<?= e($BASE) ?>/admin">
    <label class="sr-only" for="k">Admin klíč</label>
    <input id="k" name="k" type="password" autocomplete="current-password" placeholder="Admin klíč" required autofocus>
    <button class="btn btn--gold" type="submit">Vstoupit</button>
  </form>
</main>
</body>
</html>

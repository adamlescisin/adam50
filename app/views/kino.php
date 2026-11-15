<?php $pageTitle = 'Kino · ' . cfg('site_title'); $bodyClass = 'page-kino'; require __DIR__ . '/_head.php'; ?>
<div class="kino" data-kino tabindex="-1">
  <div class="kino__stage" data-stage></div>

  <div class="kino__start" data-start>
    <div class="kino__start-inner">
      <p class="eyebrow">Promítání</p>
      <h1 class="display"><?= e(cfg('site_title')) ?></h1>
      <p class="lead" data-start-meta>Načítám vzpomínky…</p>
      <button class="btn btn--gold btn--lg" data-action="start" disabled>▶ Spustit film</button>
      <label class="check check--light"><input type="checkbox" data-guess> Hra „Hádej, kdo to napsal“ – autor se odhalí až po stisku Enter</label>
      <p class="keys"><kbd>mezerník</kbd> pauza · <kbd>←</kbd><kbd>→</kbd> zpět/dál · <kbd>F</kbd> celá obrazovka · <kbd>G</kbd> přehled · <kbd>R</kbd> náhoda · <kbd>M</kbd> hudba</p>
    </div>
  </div>

  <div class="kino__hud" data-hud>
    <div class="kino__progress" data-progress><i></i></div>
    <div class="kino__controls">
      <button data-action="prev" aria-label="Předchozí">⏮</button>
      <button data-action="toggle" aria-label="Pauza">⏯</button>
      <button data-action="next" aria-label="Další">⏭</button>
      <span class="kino__chapter" data-chapter></span>
      <span class="spacer"></span>
      <button data-action="overview" aria-label="Přehled">▦</button>
      <button data-action="music" aria-label="Hudba">♫</button>
      <button data-action="fullscreen" aria-label="Celá obrazovka">⛶</button>
    </div>
  </div>

  <div class="kino__overview" data-overview hidden>
    <div class="kino__overview-head"><h2 class="h2">Přehled</h2><button class="btn btn--ghost btn--sm" data-action="overview">Zavřít (G)</button></div>
    <div class="kino__overview-grid" data-overview-grid></div>
  </div>
</div>
<?php if (cfg('kino_music')): ?><audio data-music src="<?= e((($offline ?? false) ? '' : $BASE . '/') . cfg('kino_music')) ?>" loop preload="auto"></audio><?php endif; ?>
<script>window.P50 = { showUrl: <?= json_encode($showUrl) ?> };</script>
<script src="<?= ($offline ?? false) ? 'assets' : e($BASE) . '/assets' ?>/js/kino.js?v=<?= @filemtime(dirname(__DIR__, 2) . '/public/assets/js/kino.js') ?: 1 ?>" defer></script>
</body>
</html>

<?php $pageTitle = 'Administrace · ' . cfg('site_title'); $bodyClass = 'page-admin'; require __DIR__ . '/_head.php'; $assets = $BASE . '/assets'; ?>
<header class="topbar">
  <a class="brand" href="<?= e($BASE) ?>/admin"><span class="brand__mark">50</span><span class="brand__name">Režisérská kabina</span></a>
  <nav class="topbar__me">
    <a class="btn btn--ghost btn--sm" href="<?= e($BASE) ?>/kino" target="_blank">🎬 Kino</a>
    <a class="btn btn--gold btn--sm" href="<?= e($BASE) ?>/admin/export.zip">⬇ Export pro oslavu</a>
  </nav>
</header>
<main class="admin">
  <section class="kpis" data-kpis></section>

  <section class="card">
    <h2 class="h2">Hosté</h2>
    <p class="muted">Jeden řádek = jedna domácnost (pár sdílí jeden odkaz). Volitelně za středník vztah k oslavenci.<br>
      <code>Petra a Honza; Novákovi, spolužáci z gymplu</code> – jména pište jen křestní (použijí se k oslovení a podpisu).</p>
    <form data-import class="import">
      <textarea class="input" rows="4" name="lines" placeholder="Jana a Pavel&#10;Martin; kolega z Accenture"></textarea>
      <button class="btn btn--gold" type="submit">Přidat hosty</button>
    </form>
    <div class="table-wrap"><table class="table" data-households>
      <thead><tr><th>Domácnost</th><th>Vztah</th><th>Příspěvky</th><th>Naposledy</th><th>Odkaz</th><th></th></tr></thead>
      <tbody></tbody>
    </table></div>
  </section>

  <section class="card">
    <h2 class="h2">Vzpomínky <span class="muted" data-mem-count></span></h2>
    <div class="admin-grid" data-memories></div>
  </section>
</main>

<dialog class="qr-dialog" data-qr>
  <h3 class="h3" data-qr-title></h3>
  <div data-qr-svg></div>
  <textarea class="input" rows="5" data-qr-msg readonly></textarea>
  <div class="row"><button class="btn btn--gold btn--sm" data-action="copy-msg">Zkopírovat pozvánku</button><button class="btn btn--ghost btn--sm" data-action="close-qr">Zavřít</button></div>
</dialog>
<div class="toast" data-toast role="status"></div>
<script>window.P50 = { base: <?= json_encode($BASE) ?> };</script>
<script src="<?= $assets ?>/vendor/qrcode.js"></script>
<script src="<?= $assets ?>/js/admin.js?v=<?= @filemtime(dirname(__DIR__, 2) . '/public/assets/js/admin.js') ?: 1 ?>" defer></script>
</body>
</html>

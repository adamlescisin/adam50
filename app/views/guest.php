<?php $pageTitle = cfg('site_title'); $bodyClass = 'page-guest'; require __DIR__ . '/_head.php';
$assets = ($BASE ?? '') . '/assets'; ?>
<header class="topbar">
  <a class="brand" href="<?= e($BASE) ?>/"><span class="brand__mark">50</span><span class="brand__name"><?= e(cfg('site_title')) ?></span></a>
  <div class="topbar__me"><span data-bind="hello"></span> <a class="link-muted" href="<?= e($BASE) ?>/odhlasit">Odhlásit</a></div>
</header>

<main class="guest">
  <!-- HERO -->
  <section class="hero">
    <div class="hero__years" aria-hidden="true"><span><?= (int)cfg('birth_year') ?></span><i></i><span><?= (int)cfg('birth_year') + 50 ?></span></div>
    <h1 class="display hero__title"><span data-bind="greeting">Ahoj,</span><br>pomozte poskládat <em><?= e(cfg('celebrant')) ?>ových 50&nbsp;let.</em></h1>
    <p class="lead">Napište historku, nahrajte fotky z šuplíku nebo namluvte vzkaz. Na oslavě to všechno společně pustíme jako film&nbsp;– a vy budete jeho autoři.</p>
    <div class="hero__meta">
      <div class="countdown" data-countdown aria-live="polite"></div>
      <div class="stats" data-stats></div>
    </div>
    <a class="btn btn--gold btn--lg" href="#composer">Přidat vzpomínku</a>
    <p class="hint" data-kino-link hidden><a href="<?= e($BASE) ?>/kino">🎬 Kino je otevřené – pusťte si film znovu</a></p>
  </section>

  <!-- COMPOSER -->
  <section class="card composer" id="composer" aria-labelledby="composer-title">
    <div class="composer__head">
      <h2 class="h2" id="composer-title" data-bind="composerTitle">Nová vzpomínka</h2>
      <button type="button" class="btn btn--ghost btn--sm" data-action="cancel-edit" hidden>Zrušit úpravy</button>
    </div>
    <div class="closed-note" data-closed hidden>Sběr vzpomínek je uzavřený. Díky všem, uvidíme se v kině! 🎬</div>

    <form data-composer novalidate>
      <fieldset class="step">
        <legend><span class="step__n">1</span> Kdo píše?</legend>
        <div class="chips" data-authors></div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">2</span> Kdy to bylo?</legend>
        <div class="year">
          <output class="year__out" data-year-out>1995</output>
          <span class="year__era" data-year-era></span>
          <input type="range" name="year" min="<?= (int)cfg('birth_year') ?>" max="<?= (int)date('Y') ?>" value="1995" data-year aria-label="Rok vzpomínky">
          <div class="year__ticks" aria-hidden="true"><?php for ($y = (int)ceil(cfg('birth_year') / 10) * 10; $y <= (int)date('Y'); $y += 10) echo "<span style=\"--p:" . round(($y - cfg('birth_year')) / ((int)date('Y') - cfg('birth_year')) * 100, 2) . "%\">$y</span>"; ?></div>
          <label class="check"><input type="checkbox" data-year-unknown> Nevím přesně / není to o jednom roce</label>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">3</span> Příběh</legend>
        <input class="input input--title" name="title" maxlength="140" placeholder="Nadpis, třeba „Jak jsme v roce 98 zapadli na Šumavě“">
        <div class="textarea-wrap">
          <textarea class="input" name="body" rows="6" maxlength="6000" placeholder="Pište, jako byste to vyprávěli u stolu…"></textarea>
          <div class="prompt">
            <button type="button" class="prompt__btn" data-action="prompt" title="Inspirace">✨ Došla slova?</button>
            <span class="prompt__text" data-prompt></span>
          </div>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">4</span> Fotky a videa</legend>
        <label class="drop" data-drop>
          <input type="file" accept="image/*,video/*" multiple data-files hidden>
          <span class="drop__icon" aria-hidden="true">📷</span>
          <span><strong>Vyberte fotky</strong> nebo je sem přetáhněte</span>
          <small>I staré papírové fotky – stačí je vyfotit mobilem. Videa do <?= (int)cfg('max_video_mb') ?> MB.</small>
        </label>
        <div class="thumbs" data-thumbs></div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">5</span> Hlasový vzkaz <span class="optional">nepovinné</span></legend>
        <div class="rec" data-rec>
          <button type="button" class="rec__btn" data-action="rec" aria-label="Nahrávat"><span></span></button>
          <div class="rec__body">
            <div class="rec__status" data-rec-status>Klepněte a mluvte – třeba jen 20 vteřin.</div>
            <canvas class="rec__wave" data-rec-wave width="600" height="60" aria-hidden="true"></canvas>
            <audio controls data-rec-audio hidden></audio>
          </div>
          <button type="button" class="btn btn--ghost btn--sm" data-action="rec-clear" hidden>Smazat</button>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">6</span> Štítky</legend>
        <div class="chips chips--tags" data-tags></div>
        <label class="wish">
          <input type="checkbox" name="is_wish" data-wish>
          <span class="wish__ui"><strong>🥂 Tohle je přání do dalších let</strong><small>Přání pustíme ve velkém finále.</small></span>
        </label>
      </fieldset>

      <div class="submit">
        <div class="progress" data-progress hidden><i></i></div>
        <button class="btn btn--gold btn--lg" type="submit" data-submit>Uložit vzpomínku</button>
        <p class="error" data-error role="alert"></p>
      </div>
    </form>
  </section>

  <!-- MOJE -->
  <section class="mine" aria-labelledby="mine-title">
    <h2 class="h2" id="mine-title">Vaše vzpomínky <span class="count" data-mine-count></span></h2>
    <div class="grid" data-mine></div>
    <p class="empty" data-mine-empty>Zatím nic. První vzpomínka je vždycky nejtěžší – stačí jedna fotka. 🙂</p>
  </section>

  <!-- ZEĎ -->
  <section class="wall" data-wall-section hidden aria-labelledby="wall-title">
    <h2 class="h2" id="wall-title">Co poslali ostatní</h2>
    <div class="masonry" data-wall></div>
  </section>
  <section class="teaser" data-teaser hidden>
    <div class="teaser__envelope" aria-hidden="true">✉️</div>
    <p>Příspěvky ostatních zůstávají zapečetěné až do oslavy. Překvapení musí být!</p>
  </section>

  <footer class="foot">
    <p>Příspěvky vidí jen pozvaní hosté a použijí se pro promítání na oslavě. Z fotek automaticky odstraňujeme polohu (GPS). Svoje vzpomínky můžete kdykoliv upravit nebo smazat.</p>
  </footer>
</main>

<div class="toast" data-toast role="status" aria-live="polite"></div>
<dialog class="lightbox" data-lightbox><img alt=""><button class="lightbox__close" data-action="lb-close" aria-label="Zavřít">×</button></dialog>

<script>window.P50 = { base: <?= json_encode($BASE) ?> };</script>
<script src="<?= $assets ?>/js/app.js?v=<?= @filemtime(dirname(__DIR__, 2) . '/public/assets/js/app.js') ?: 1 ?>" defer></script>
</body>
</html>

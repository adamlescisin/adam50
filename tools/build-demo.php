<?php
/**
 * Postaví samostatný klikací náhled (jeden HTML soubor, bez serveru):
 * stránka hosta s falešným API + Kino jako překryvná vrstva. Data bere z lokální DB.
 *   php tools/seed-demo.php --reset && php tools/build-demo.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/media.php';
$root = dirname(__DIR__);

function data_url(string $path, int $edge = 0, int $q = 78): string {
    if ($edge) {
        $im = imagecreatefromjpeg($path);
        $r = resize_to($im, $edge);
        ob_start(); imagejpeg($r, null, $q); $bin = ob_get_clean();
    } else $bin = file_get_contents($path);
    return 'data:image/jpeg;base64,' . base64_encode($bin);
}

$show = show_payload('');
foreach ($show['memories'] as &$m) {
    foreach ($m['media'] as &$x) {
        $dir = memory_dir($m['id']);
        if ($x['kind'] === 'photo') {
            $x['url'] = $x['file'] = data_url("$dir/{$x['file']}", 1400, 74);
            $x['thumbUrl'] = $x['thumb'] = data_url("$dir/{$x['thumb']}", 480, 70);
        }
    }
}
unset($m, $x);

// CSS s vloženými fonty
$css = file_get_contents("$root/public/assets/css/app.css");
$css = preg_replace_callback('#url\(\.\./fonts/([\w.-]+)\)#', fn($mm) => 'url(data:font/woff2;base64,' . base64_encode(file_get_contents("$root/public/assets/fonts/{$mm[1]}")) . ')', $css);

$guest = render_view('guest', ['BASE' => '']);
$kinoHtml = render_view('kino', ['BASE' => '', 'offline' => true, 'showUrl' => '']);
preg_match('#<div class="kino" data-kino.*?(?=<script>window\.P50)#s', $kinoHtml, $km);

$me = [
    'household' => ['label' => 'Petra a Honza', 'names' => ['Petra', 'Honza']],
    'config' => [
        'celebrant' => cfg('celebrant'), 'birth_year' => (int)cfg('birth_year'), 'party_date' => cfg('party_date'),
        'party_place' => cfg('party_place'), 'title' => cfg('site_title'), 'tags' => cfg('tags'),
        'submissions_open' => true, 'max_photos' => 12, 'max_video_mb' => 200, 'kino_open' => false,
    ],
    'media_base' => '',
];
$demoJs = 'const SHOW = ' . json_encode($show, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n"
        . 'const ME = ' . json_encode($me, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n" . <<<'JS'
const mine = SHOW.memories.filter(m => m.household.label === ME.household.label);
const others = SHOW.memories.filter(m => m.household.label !== ME.household.label);
const photosCount = () => SHOW.memories.reduce((n, m) => n + m.media.filter(x => x.kind === 'photo').length, 0);
try { window.confirm = () => true; } catch {}
window.P50 = {
  base: '',
  demoShow: SHOW,
  demo: {
    async me() {
      return { ...ME, mine: mine.slice().reverse(), wall: others,
        stats: { memories: SHOW.memories.length, photos: photosCount(), households: new Set(SHOW.memories.map(m => m.household.label)).size, households_total: 14 } };
    },
    async save(fd, id, onProgress) {
      for (let p = 0; p <= 1; p += .1) { onProgress(p); await new Promise(r => setTimeout(r, 60)); }
      const old = id ? mine.find(m => m.id === id) : null;
      const media = (old ? old.media.filter(x => !fd.getAll('remove_media[]').includes(String(x.id))) : []);
      let n = Date.now();
      for (const f of fd.getAll('files[]')) { const u = URL.createObjectURL(f); media.push({ id: n++, kind: f.type.startsWith('video/') ? 'video' : 'photo', file: u, thumb: u, url: u, thumbUrl: u }); }
      const a = fd.get('audio'); if (a) { const u = URL.createObjectURL(a); media.push({ id: n++, kind: 'audio', file: u, url: u }); }
      const m = Object.assign(old || { id: crypto.randomUUID(), created_at: new Date().toISOString(), household: { label: ME.household.label } }, {
        authors: fd.getAll('authors[]'), title: fd.get('title'), body: fd.get('body'), year: fd.get('year') ? +fd.get('year') : null,
        tags: fd.getAll('tags[]'), wish_text: fd.get('wish_text') || null, wish_public: !!fd.get('wish_public'), status: 'visible', media });
      if (!old) { mine.push(m); SHOW.memories.push(m); }
      return m;
    },
    async del(id) { [mine, SHOW.memories].forEach(arr => { const i = arr.findIndex(m => m.id === id); if (i >= 0) arr.splice(i, 1); }); },
  },
};
JS;

$switcher = <<<'HTML'
<div class="demo-bar"><span>Náhled</span><button class="btn btn--gold btn--sm" data-open-kino>▶ Pustit Kino</button></div>
<div id="kino-wrap" hidden>
  <button class="btn btn--ghost btn--sm demo-close" data-close-kino>× Zavřít kino</button>
  %KINO%
</div>
<style>
.demo-bar{position:fixed;right:1rem;bottom:1rem;z-index:300;display:flex;align-items:center;gap:.7rem;padding:.5rem .6rem .5rem 1rem;border-radius:999px;background:rgba(29,25,21,.92);box-shadow:var(--shadow);backdrop-filter:blur(10px);font-size:.85rem;color:var(--paper-dim)}
#kino-wrap{position:fixed;inset:0;z-index:400;background:#000}
.demo-close{position:fixed;top:1rem;right:1rem;z-index:500;background:rgba(0,0,0,.5)}
</style>
<script type="text/plain" id="kino-src">%KINOJS%</script>
<script>
document.querySelector('[data-open-kino]').addEventListener('click', () => {
  const w = document.getElementById('kino-wrap'); w.hidden = false; document.body.style.overflow = 'hidden';
  if (!window.__kino) { window.__kino = 1; const s = document.createElement('script'); s.textContent = document.getElementById('kino-src').textContent; document.body.append(s); }
});
document.querySelector('[data-close-kino]').addEventListener('click', () => location.reload());
</script>
HTML;
$switcher = str_replace(['%KINO%', '%KINOJS%'], [$km[0], str_replace('</script', '<\/script', file_get_contents("$root/public/assets/js/kino.js"))], $switcher);

$html = preg_replace('#<link rel="preload"[^>]+>\s*#', '', $guest);
$html = preg_replace_callback('#<link rel="stylesheet"[^>]+>#', fn() => "<style>\n$css\n</style>", $html);
$html = preg_replace_callback('#<script>window\.P50 = .*?</script>\s*<script src="[^"]+app\.js[^"]*" defer></script>#s',
    fn() => "<script>\n$demoJs\n</script>\n<script>\n" . file_get_contents("$root/public/assets/js/app.js") . "\n</script>\n" . $switcher, $html);
$html = str_replace(', viewport-fit=cover', '', $html);
$html = str_replace('href="/odhlasit"', 'href="#" onclick="return false"', $html);

@mkdir("$root/demo/dist", 0775, true);
file_put_contents("$root/demo/dist/index.html", $html);
printf("demo/dist/index.html (%.1f MB)\n", strlen($html) / 1048576);

<?php
declare(strict_types=1);

// app/ leží o úroveň výš než webroot (doporučeno), případně vedle index.php
$APP = is_dir(__DIR__ . '/../app') ? __DIR__ . '/../app' : __DIR__ . '/app';
require $APP . '/bootstrap.php';
require $APP . '/media.php';

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$BASE = basename($script) === 'index.php' ? rtrim(dirname($script), '/') : ''; // podpora instalace do podsložky
// nasazení celého repa s kořenovým .htaccess → URL nemají obsahovat /public
if (str_ends_with($BASE, '/public') && !str_starts_with((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $BASE)) $BASE = substr($BASE, 0, -7);
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', strlen($BASE)), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Vestavěný PHP server (lokální vývoj / Mac Mini): statické soubory obsloužit přímo
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) return false;

header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

try {
    route($method, $path, $BASE);
} catch (Throwable $ex) {
    error_log('[p50] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (str_starts_with($path, '/api/')) fail($ex instanceof RuntimeException ? $ex->getMessage() : 'Něco se pokazilo, zkus to prosím znovu.', 500);
    http_response_code(500);
    echo 'Něco se pokazilo.';
}

function route(string $method, string $path, string $BASE): void {
    $seg = explode('/', trim($path, '/'));

    /* ---------- stránky ---------- */
    if ($method === 'GET' && $seg[0] === 'g' && isset($seg[1])) {
        $h = login_with_token($seg[1]);
        header('Location: ' . ($BASE ?: '') . '/' . ($h ? '' : '?neplatny=1'));
        exit;
    }
    if ($method === 'POST' && $path === '/prihlasit') {
        $h = login_with_token((string)($_POST['kod'] ?? ''));
        header('Location: ' . ($BASE ?: '') . '/' . ($h ? '' : '?neplatny=1'));
        exit;
    }
    if ($method === 'GET' && $path === '/odhlasit') {
        start_session(); $_SESSION = []; session_destroy();
        header('Location: ' . ($BASE ?: '') . '/'); exit;
    }
    if ($method === 'GET' && $path === '/') {
        echo render_view(current_household() ? 'guest' : 'landing', ['BASE' => $BASE]);
        return;
    }
    if ($method === 'GET' && $path === '/kino') {
        if (!is_admin() && !(kino_public() && current_household())) {
            http_response_code(403);
            echo render_view('landing', ['BASE' => $BASE, 'notice' => 'Kino se otevře až na oslavě. 🎬']);
            return;
        }
        echo render_view('kino', ['BASE' => $BASE, 'offline' => false, 'showUrl' => "$BASE/api/show"]);
        return;
    }
    if ($method === 'POST' && $path === '/admin') {
        $k = (string)cfg('admin_key'); start_session();
        if ($k !== '' && $k !== 'ZMEN-MNE' && hash_equals($k, (string)($_POST['k'] ?? ''))) { session_regenerate_id(true); $_SESSION['admin'] = true; }
        else { usleep(800000); $_SESSION['admin_fail'] = true; }
        header('Location: ' . ($BASE ?: '') . '/admin'); exit;
    }
    if ($method === 'GET' && $path === '/admin') {
        if (!is_admin()) { // přihlašovací formulář (klíč může obsahovat #, & apod., které v URL nefungují)
            start_session(); $fail = !empty($_SESSION['admin_fail']); unset($_SESSION['admin_fail']);
            echo render_view('admin_login', ['BASE' => $BASE, 'fail' => $fail]); return;
        }
        if (isset($_GET['k'])) { header('Location: ' . ($BASE ?: '') . '/admin'); exit; } // klíč pryč z URL/historie
        echo render_view('admin', ['BASE' => $BASE]);
        return;
    }
    if ($method === 'GET' && $path === '/admin/export.zip') {
        if (!is_admin()) { http_response_code(404); exit; }
        @set_time_limit(600);
        $f = build_export_zip(sys_get_temp_dir() . '/p50-export-' . date('Ymd-His') . '.zip');
        header('Content-Disposition: attachment; filename="' . basename($f) . '"');
        serve_file($f, 'application/zip');
    }

    /* ---------- média (s kontrolou oprávnění) ---------- */
    if ($method === 'GET' && $seg[0] === 'm' && count($seg) === 3) {
        [$_, $mid, $file] = $seg;
        if (!preg_match('/^[0-9a-f-]{36}$/', $mid) || !preg_match('/^[\w.-]+$/', $file)) { http_response_code(404); exit; }
        $m = load_memory($mid);
        if (!$m || !can_view_memory($m)) { http_response_code(404); exit; }
        $st = db()->prepare('SELECT mime FROM media WHERE memory_id = ? AND (file = ? OR thumb = ?)');
        $st->execute([$mid, $file, $file]);
        $mime = $st->fetchColumn();
        if (!$mime) { http_response_code(404); exit; }
        if (str_ends_with($file, '.thumb.jpg')) $mime = 'image/jpeg';
        serve_file(memory_dir($mid) . '/' . $file, $mime);
    }

    /* ---------- API ---------- */
    if (!str_starts_with($path, '/api/')) { http_response_code(404); echo render_view('landing', ['BASE' => $BASE, 'notice' => 'Stránka nenalezena.']); return; }
    if ($method !== 'GET') require_xhr();
    $api = array_slice($seg, 1);

    if ($api[0] === 'show' && $method === 'GET') {
        if (!is_admin() && !(kino_public() && current_household())) fail('Zatím zamčeno', 403);
        json_out(show_payload("$BASE/m"));
    }

    if ($api[0] === 'admin') { admin_api($method, array_slice($api, 1), $BASE); return; }

    $h = current_household();
    if (!$h) fail('Nejsi přihlášený – otevři svůj osobní odkaz z pozvánky.', 401);

    if ($api[0] === 'me' && $method === 'GET') {
        $st = db()->prepare("SELECT m.*, h.label AS h_label, h.relation AS h_relation FROM memories m
                             JOIN households h ON h.id = m.household_id WHERE m.household_id = ? ORDER BY m.created_at DESC");
        $st->execute([$h['id']]);
        $mine = array_map('memory_to_array', $st->fetchAll());
        $stats = db()->query("SELECT COUNT(*) AS memories,
                                (SELECT COUNT(*) FROM media WHERE kind='photo') AS photos,
                                COUNT(DISTINCT household_id) AS households FROM memories WHERE status='visible'")->fetch();
        $stats['households_total'] = (int)db()->query('SELECT COUNT(*) FROM households')->fetchColumn();
        $wallOpen = cfg('wall_visible_before_party') || party_started();
        json_out([
            'household' => ['label' => $h['label'], 'names' => $h['names']],
            'config' => [
                'celebrant' => cfg('celebrant'), 'birth_year' => (int)cfg('birth_year'),
                'party_date' => cfg('party_date'), 'party_place' => cfg('party_place'),
                'title' => cfg('site_title'), 'tags' => cfg('tags'),
                'submissions_open' => submissions_open(), 'submissions_close' => cfg('submissions_close'),
                'max_photos' => (int)cfg('max_photos_per_memory'), 'max_video_mb' => (int)cfg('max_video_mb'),
                'kino_open' => kino_public(),
            ],
            'mine' => $mine,
            'stats' => array_map('intval', $stats),
            'wall' => $wallOpen ? array_values(array_filter(all_memories(false), fn($m) => $m['household']['id'] !== (int)$h['id'])) : null,
            'media_base' => "$BASE/m",
        ]);
    }

    if ($api[0] === 'memories') {
        $id = $api[1] ?? null;
        if ($method === 'POST') {
            if (!submissions_open()) fail('Sběr vzpomínek už je uzavřený. Díky! 🎉', 403);
            json_out(save_memory($h, $id));
        }
        if ($method === 'DELETE' && $id) {
            $m = load_memory($id);
            if (!$m || (int)$m['household_id'] !== (int)$h['id']) fail('Nenalezeno', 404);
            db()->prepare('DELETE FROM memories WHERE id = ?')->execute([$id]);
            rrmdir(memory_dir($id));
            json_out(['ok' => true]);
        }
    }
    fail('Neznámý požadavek', 404);
}

function can_view_memory(array $m): bool {
    if (is_admin()) return true;
    $h = current_household();
    if (!$h) return false;
    if ((int)$m['household_id'] === (int)$h['id']) return true;
    if ($m['status'] !== 'visible') return false;
    return cfg('wall_visible_before_party') || party_started() || kino_public();
}

/** Vytvoření nebo úprava vzpomínky (multipart/form-data). */
function save_memory(array $h, ?string $id): array {
    $pdo = db();
    $isNew = $id === null;
    if (!$isNew) {
        $m = load_memory($id);
        if (!$m || (int)$m['household_id'] !== (int)$h['id']) fail('Nenalezeno', 404);
    }

    $authors = array_values(array_intersect($h['names'], (array)($_POST['authors'] ?? [])));
    if (!$authors) $authors = $h['names'];
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 140);
    $body  = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 6000);
    $year  = (int)($_POST['year'] ?? 0);
    $year  = ($year >= (int)cfg('birth_year') && $year <= (int)date('Y')) ? $year : null;
    $tags  = array_values(array_intersect(cfg('tags'), (array)($_POST['tags'] ?? [])));
    $wish  = !empty($_POST['is_wish']) ? 1 : 0;

    $files = normalize_files($_FILES['files'] ?? null);
    $audio = isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK ? $_FILES['audio'] : null;
    $removeIds = array_map('intval', (array)($_POST['remove_media'] ?? []));

    $existing = 0;
    if (!$isNew) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM media WHERE memory_id = ?'); $c->execute([$id]);
        $existing = (int)$c->fetchColumn() - count($removeIds);
    }
    if ($body === '' && $title === '' && !$files && !$audio && $existing <= 0) fail('Napiš pár slov nebo přidej fotku či vzkaz. 🙂');
    if (count($files) + max(0, $existing) > (int)cfg('max_photos_per_memory') + 2) fail('Moc souborů najednou – rozděl je prosím do více vzpomínek.');

    $pdo->beginTransaction();
    try {
        if ($isNew) {
            $id = uuid4();
            $pdo->prepare('INSERT INTO memories (id, household_id, authors_json, title, body, year, tags_json, is_wish, created_at, updated_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id, $h['id'], json_encode($authors, JSON_UNESCAPED_UNICODE), $title, $body, $year,
                           json_encode($tags, JSON_UNESCAPED_UNICODE), $wish, now(), now()]);
        } else {
            $pdo->prepare('UPDATE memories SET authors_json=?, title=?, body=?, year=?, tags_json=?, is_wish=?, updated_at=? WHERE id=?')
                ->execute([json_encode($authors, JSON_UNESCAPED_UNICODE), $title, $body, $year,
                           json_encode($tags, JSON_UNESCAPED_UNICODE), $wish, now(), $id]);
            foreach ($removeIds as $mid) {
                $q = $pdo->prepare('SELECT file, thumb FROM media WHERE id = ? AND memory_id = ?'); $q->execute([$mid, $id]);
                if ($row = $q->fetch()) {
                    foreach ([$row['file'], $row['thumb']] as $f) if ($f) @unlink(memory_dir($id) . "/$f");
                    $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$mid]);
                }
            }
        }
        $sort = (int)$pdo->query("SELECT COALESCE(MAX(sort), -1) + 1 FROM media WHERE memory_id = " . $pdo->quote($id))->fetchColumn();
        $ins = $pdo->prepare('INSERT INTO media (memory_id, kind, file, thumb, mime, width, height, bytes, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $uploads = array_map(fn($f) => [$f, 'auto'], $files);
        if ($audio) $uploads[] = [$audio, 'audio'];
        foreach ($uploads as [$f, $kind]) {
            $r = store_upload($id, $f, $kind, $sort);
            $ins->execute([$id, $r['kind'], $r['file'], $r['thumb'], $r['mime'], $r['width'], $r['height'], $r['bytes'], $sort++, now()]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        if ($isNew) rrmdir(memory_dir($id));
        throw $ex;
    }
    write_sidecar($id);
    return memory_to_array(load_memory($id));
}

function normalize_files(?array $f): array {
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/* ---------- administrace ---------- */
function admin_api(string $method, array $a, string $BASE): void {
    if (!is_admin()) fail('Not found', 404);
    $pdo = db();
    if (($a[0] ?? '') === 'state' && $method === 'GET') {
        $hh = $pdo->query('SELECT h.*, (SELECT COUNT(*) FROM memories m WHERE m.household_id = h.id) AS memories
                           FROM households h ORDER BY h.label COLLATE NOCASE')->fetchAll();
        foreach ($hh as &$h) {
            $h['names'] = json_decode($h['names_json'], true); unset($h['names_json']);
            $h['link'] = rtrim((string)cfg('base_url'), '/') . '/g/' . $h['token'];
        }
        json_out(['households' => $hh, 'memories' => all_memories(true), 'media_base' => "$BASE/m",
                  'config' => ['celebrant' => cfg('celebrant'), 'party_date' => cfg('party_date'), 'title' => cfg('site_title')]]);
    }
    if (($a[0] ?? '') === 'households' && $method === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        if (isset($a[1])) { // úprava / smazání
            $hid = (int)$a[1];
            if (!empty($in['delete'])) {
                foreach ($pdo->query('SELECT id FROM memories WHERE household_id = ' . $hid)->fetchAll() as $m) rrmdir(memory_dir($m['id']));
                $pdo->prepare('DELETE FROM households WHERE id = ?')->execute([$hid]);
            } else {
                $label = trim((string)($in['label'] ?? ''));
                $pdo->prepare('UPDATE households SET label = ?, names_json = ?, relation = ? WHERE id = ?')
                    ->execute([$label, json_encode(split_names($label), JSON_UNESCAPED_UNICODE), trim((string)($in['relation'] ?? '')) ?: null, $hid]);
            }
            json_out(['ok' => true]);
        }
        // hromadný import: jeden řádek = jedna domácnost, volitelně "; vztah"
        $added = 0;
        foreach (preg_split('/\R/u', (string)($in['lines'] ?? '')) as $line) {
            [$label, $rel] = array_map('trim', explode(';', $line, 2) + [1 => '']);
            if ($label === '') continue;
            $pdo->prepare('INSERT INTO households (token, label, names_json, relation, created_at) VALUES (?,?,?,?,?)')
                ->execute([token(), $label, json_encode(split_names($label), JSON_UNESCAPED_UNICODE), $rel ?: null, now()]);
            $added++;
        }
        json_out(['added' => $added]);
    }
    if (($a[0] ?? '') === 'memories' && isset($a[1]) && $method === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($in['delete'])) {
            $pdo->prepare('DELETE FROM memories WHERE id = ?')->execute([$a[1]]);
            rrmdir(memory_dir($a[1]));
        } elseif (isset($in['status'])) {
            $pdo->prepare('UPDATE memories SET status = ? WHERE id = ?')->execute([$in['status'] === 'hidden' ? 'hidden' : 'visible', $a[1]]);
            write_sidecar($a[1]);
        }
        json_out(['ok' => true]);
    }
    fail('Neznámý požadavek', 404);
}

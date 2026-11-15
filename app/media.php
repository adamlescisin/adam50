<?php
declare(strict_types=1);

const PHOTO_MAX_EDGE = 2560;
const THUMB_EDGE     = 640;

const AUDIO_MIMES = [
    'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a',
    'audio/aac' => 'aac', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
    'video/webm' => 'webm', // Chrome někdy hlásí nahrávku z mikrofonu jako video/webm
];
const VIDEO_MIMES = [
    'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/x-m4v' => 'm4v',
];

function memory_dir(string $memoryId): string {
    if (!preg_match('/^[0-9a-f-]{36}$/', $memoryId)) throw new RuntimeException('bad id');
    return data_dir('media/' . $memoryId);
}

/**
 * Uloží nahraný soubor k vzpomínce. Fotky se znovu zakódují přes GD:
 *  - odstraní EXIF/GPS (soukromí hostů), zmenší na 2560 px, vytvoří náhled 640 px,
 *  - zároveň tím zneškodní cokoliv, co se tváří jako obrázek, ale není.
 */
function store_upload(string $memoryId, array $file, string $declaredKind, int $sort): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Soubor se nepodařilo nahrát (kód ' . ($file['error'] ?? '?') . ').');
    }
    $tmp  = $file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $dir  = memory_dir($memoryId);
    $base = sprintf('%02d-%s', $sort, substr(bin2hex(random_bytes(4)), 0, 6));
    $bytes = (int)filesize($tmp);

    if (str_starts_with($mime, 'image/')) {
        $src = @imagecreatefromstring((string)file_get_contents($tmp));
        if (!$src) throw new RuntimeException('Obrázek se nepodařilo přečíst (zkus JPG nebo PNG).');
        $src = exif_orient($src, $tmp, $mime);
        [$w, $h] = [imagesx($src), imagesy($src)];
        $full  = resize_to($src, PHOTO_MAX_EDGE);
        $thumb = resize_to($src, THUMB_EDGE);
        imagejpeg($full,  "$dir/$base.jpg", 86);
        imagejpeg($thumb, "$dir/$base.thumb.jpg", 78);
        $out = ['kind' => 'photo', 'file' => "$base.jpg", 'thumb' => "$base.thumb.jpg", 'mime' => 'image/jpeg',
                'width' => imagesx($full), 'height' => imagesy($full), 'bytes' => (int)filesize("$dir/$base.jpg")];
        imagedestroy($src); imagedestroy($full); imagedestroy($thumb);
        return $out;
    }

    if ($declaredKind === 'audio' && isset(AUDIO_MIMES[$mime])) {
        if ($bytes > cfg('max_audio_mb') * 1048576) throw new RuntimeException('Hlasová zpráva je příliš dlouhá.');
        $name = "$base." . AUDIO_MIMES[$mime];
        move_uploaded_file($tmp, "$dir/$name") || rename($tmp, "$dir/$name");
        return ['kind' => 'audio', 'file' => $name, 'thumb' => null, 'mime' => $mime === 'video/webm' ? 'audio/webm' : $mime,
                'width' => null, 'height' => null, 'bytes' => $bytes];
    }

    if (isset(VIDEO_MIMES[$mime])) {
        if ($bytes > cfg('max_video_mb') * 1048576) throw new RuntimeException('Video je větší než ' . cfg('max_video_mb') . ' MB.');
        $name = "$base." . VIDEO_MIMES[$mime];
        move_uploaded_file($tmp, "$dir/$name") || rename($tmp, "$dir/$name");
        return ['kind' => 'video', 'file' => $name, 'thumb' => null, 'mime' => $mime,
                'width' => null, 'height' => null, 'bytes' => $bytes];
    }

    throw new RuntimeException("Tenhle typ souboru neumím uložit ($mime).");
}

function exif_orient(GdImage $img, string $path, string $mime): GdImage {
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) return $img;
    $o = (int)(@exif_read_data($path)['Orientation'] ?? 1);
    return match ($o) {
        3 => imagerotate($img, 180, 0),
        6 => imagerotate($img, -90, 0),
        8 => imagerotate($img, 90, 0),
        default => $img,
    };
}

function resize_to(GdImage $src, int $edge): GdImage {
    [$w, $h] = [imagesx($src), imagesy($src)];
    $s = min(1, $edge / max($w, $h));
    [$nw, $nh] = [max(1, (int)round($w * $s)), max(1, (int)round($h * $s))];
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // průhledné PNG -> bílé pozadí
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** Vrátí vzpomínku jako pole připravené pro JSON (API i export). */
function memory_to_array(array $m, bool $withHousehold = true): array {
    $st = db()->prepare('SELECT * FROM media WHERE memory_id = ? ORDER BY sort, id');
    $st->execute([$m['id']]);
    $media = array_map(fn($x) => [
        'id' => (int)$x['id'], 'kind' => $x['kind'], 'file' => $x['file'], 'thumb' => $x['thumb'],
        'mime' => $x['mime'], 'width' => $x['width'] ? (int)$x['width'] : null,
        'height' => $x['height'] ? (int)$x['height'] : null, 'bytes' => (int)$x['bytes'],
    ], $st->fetchAll());
    $out = [
        'id' => $m['id'],
        'authors' => json_decode($m['authors_json'], true),
        'title' => $m['title'], 'body' => $m['body'],
        'year' => $m['year'] !== null ? (int)$m['year'] : null,
        'tags' => json_decode($m['tags_json'], true),
        'wish_text' => $m['wish_text'] ?? null,
        'wish_public' => (bool)$m['is_wish'],
        'status' => $m['status'],
        'created_at' => $m['created_at'], 'updated_at' => $m['updated_at'],
        'media' => $media,
    ];
    if ($withHousehold) {
        $out['household'] = ['id' => (int)$m['household_id'], 'label' => $m['h_label'] ?? null, 'relation' => $m['h_relation'] ?? null];
    }
    return $out;
}

/**
 * Ke každé vzpomínce zapíše i memory.json do její složky.
 * Složka data/media/<id>/ je tak samopopisná i bez databáze – pojistka pro konsolidaci.
 */
function write_sidecar(string $memoryId): void {
    $m = load_memory($memoryId);
    if (!$m) return;
    file_put_contents(memory_dir($memoryId) . '/memory.json',
        json_encode(memory_to_array($m), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function load_memory(string $id): ?array {
    $st = db()->prepare('SELECT m.*, h.label AS h_label, h.relation AS h_relation
                         FROM memories m JOIN households h ON h.id = m.household_id WHERE m.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Soukromé přání (text i hlasovku) vidí jen autoři a admin – z veřejných výpisů ho odstraníme. */
function strip_private_wish(array $m): array {
    if ($m['wish_public']) return $m;
    $m['wish_text'] = null;
    $m['media'] = array_values(array_filter($m['media'], fn($x) => $x['kind'] !== 'audio'));
    return $m;
}

function all_memories(bool $includeHidden = false): array {
    $sql = 'SELECT m.*, h.label AS h_label, h.relation AS h_relation
            FROM memories m JOIN households h ON h.id = m.household_id'
         . ($includeHidden ? '' : " WHERE m.status = 'visible'")
         . ' ORDER BY COALESCE(m.year, 9999), m.created_at';
    return array_map('memory_to_array', db()->query($sql)->fetchAll());
}

/** Dataset pro Kino i export – jediný formát, se kterým pracuje prezentace. */
function show_payload(string $mediaBase, bool $includePrivate = false): array {
    return [
        'schema' => 'p50.show/' . SCHEMA_VERSION,
        'generated_at' => now(),
        'celebrant' => cfg('celebrant'),
        'celebrant_vocative' => cfg('celebrant_vocative'),
        'birth_year' => (int)cfg('birth_year'),
        'title' => cfg('site_title'),
        'party_date' => cfg('party_date'),
        'music' => cfg('kino_music'),
        'media_base' => $mediaBase, // URL prefix; média: {media_base}/{memory_id}/{file}
        'memories' => $includePrivate ? all_memories(false) : array_map('strip_private_wish', all_memories(false)),
    ];
}

/** Servíruje soubor s podporou HTTP Range (Safari bez ní nepřehraje audio/video). */
function serve_file(string $path, string $mime): never {
    if (!is_file($path)) { http_response_code(404); exit; }
    $size = filesize($path);
    $start = 0; $end = $size - 1;
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=31536000, immutable');
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r)) {
        if ($r[1] === '' && $r[2] !== '') { $start = max(0, $size - (int)$r[2]); }
        else { $start = (int)$r[1]; if ($r[2] !== '') $end = min((int)$r[2], $size - 1); }
        if ($start > $end || $start >= $size) {
            http_response_code(416); header("Content-Range: bytes */$size"); exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    $len = $end - $start + 1;
    header('Content-Length: ' . $len);
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    while ($len > 0 && !feof($fp)) {
        $chunk = fread($fp, (int)min(1 << 16, $len));
        echo $chunk; $len -= strlen($chunk);
        flush();
    }
    fclose($fp);
    exit;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$dir/$f") ? rrmdir("$dir/$f") : unlink("$dir/$f");
    }
    rmdir($dir);
}

/**
 * Offline balíček pro oslavu: Kino + všechna média + show.json + guests.json.
 * Rozbalíš na Macu, spustíš `python3 -m http.server` a promítáš bez internetu.
 */
function build_export_zip(string $target): string {
    $root = dirname(__DIR__);
    $zip = new ZipArchive();
    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('zip');
    $show = show_payload('media'); // jen veřejné – tohle se promítá
    $zip->addFromString('private-wishes.json', json_encode(array_values(array_filter(all_memories(true), fn($m) => !$m['wish_public'] && ($m['wish_text'] || array_filter($m['media'], fn($x) => $x['kind'] === 'audio')))),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('show.json', json_encode($show, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $hh = db()->query('SELECT id, label, names_json, relation, last_seen FROM households ORDER BY label')->fetchAll();
    foreach ($hh as &$h) { $h['names'] = json_decode($h['names_json'], true); unset($h['names_json']); }
    $zip->addFromString('guests.json', json_encode($hh, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // Kino jako statická stránka
    $zip->addFromString('index.html', render_view('kino', ['offline' => true, 'showUrl' => 'show.json']));
    foreach (['assets/css/app.css', 'assets/js/kino.js'] as $a) $zip->addFile("$root/public/$a", $a);
    foreach (glob("$root/public/assets/fonts/*.woff2") as $f) $zip->addFile($f, 'assets/fonts/' . basename($f));
    foreach (glob("$root/public/assets/music/*") ?: [] as $f) $zip->addFile($f, 'assets/music/' . basename($f));
    // Média – včetně skrytých vzpomínek (ať je nic neztratíme), memory.json u každé
    foreach (glob(data_dir('media') . '/*', GLOB_ONLYDIR) as $d) {
        foreach (glob("$d/*") as $f) $zip->addFile($f, 'media/' . basename($d) . '/' . basename($f));
    }
    $zip->close();
    return $target;
}

function render_view(string $name, array $vars = []): string {
    extract($vars);
    ob_start();
    require __DIR__ . "/views/$name.php";
    return (string)ob_get_clean();
}

<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

const SCHEMA_VERSION = 2;

function cfg(?string $key = null, $default = null) {
    static $cfg = null;
    if ($key === '__source') { cfg(); return $GLOBALS['P50_CFG_SOURCE'] ?? null; }
    if ($cfg === null) {
        // Pořadí: env P50_CONFIG → soukromá složka mimo web (Hostinger Git deploy přepisuje složku repa)
        //         → app/config.php (lokálně / Docker) → ukázkový config
        $candidates = array_filter([
            getenv('P50_CONFIG') ?: null,
            dirname(__DIR__, 3) . '/adam50-private/config.php', // ~/domains/<doména>/adam50-private/
            // pro případ, že je webroot symlink (pak __DIR__ ukazuje jinam než DOCUMENT_ROOT)
            !empty($_SERVER['DOCUMENT_ROOT']) ? dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) . '/adam50-private/config.php' : null,
            (getenv('HOME') && !empty($_SERVER['HTTP_HOST'])) ? getenv('HOME') . '/domains/' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST']) . '/adam50-private/config.php' : null,
            __DIR__ . '/config.php',
            __DIR__ . '/config.example.php',
        ]);
        $file = null;
        foreach ($candidates as $c) { if (@is_file($c)) { $file = $c; break; } }
        $GLOBALS['P50_CFG_SOURCE'] = basename(dirname($file)) . '/' . basename($file);
        $cfg = require $file;
    }
    return $key === null ? $cfg : ($cfg[$key] ?? $default);
}

function data_dir(string $sub = ''): string {
    $d = rtrim((string)cfg('data_dir'), '/');
    $p = $sub === '' ? $d : "$d/$sub";
    if (!is_dir($p)) mkdir($p, 0775, true);
    return $p;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . data_dir() . '/party.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= SCHEMA_VERSION) return;
    if ($v < 1) $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS households (
        id          INTEGER PRIMARY KEY,
        token       TEXT NOT NULL UNIQUE,      -- osobní odkaz /g/<token>
        label       TEXT NOT NULL,             -- "Petra a Honza"
        names_json  TEXT NOT NULL,             -- ["Petra","Honza"]
        relation    TEXT,                      -- volitelně: "spolužáci z gymplu"
        created_at  TEXT NOT NULL,
        last_seen   TEXT
    );
    CREATE TABLE IF NOT EXISTS memories (
        id           TEXT PRIMARY KEY,         -- UUID, zároveň název složky s médii
        household_id INTEGER NOT NULL REFERENCES households(id) ON DELETE CASCADE,
        authors_json TEXT NOT NULL,            -- kdo konkrétně píše (podmnožina names_json)
        title        TEXT,
        body         TEXT,
        year         INTEGER,                  -- přibližný rok vzpomínky
        tags_json    TEXT NOT NULL DEFAULT '[]',
        is_wish      INTEGER NOT NULL DEFAULT 0, -- = přání je veřejné (smí do finále Kina)
        status       TEXT NOT NULL DEFAULT 'visible', -- visible | hidden
        created_at   TEXT NOT NULL,
        updated_at   TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS media (
        id         INTEGER PRIMARY KEY,
        memory_id  TEXT NOT NULL REFERENCES memories(id) ON DELETE CASCADE,
        kind       TEXT NOT NULL,              -- photo | audio | video
        file       TEXT NOT NULL,              -- relativně k data/media/<memory_id>/
        thumb      TEXT,
        mime       TEXT NOT NULL,
        width      INTEGER, height INTEGER,
        duration   REAL,
        bytes      INTEGER NOT NULL,
        sort       INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS ix_mem_household ON memories(household_id);
    CREATE INDEX IF NOT EXISTS ix_media_memory ON media(memory_id);
    SQL);
    if ($v < 2) { // v2: textové přání (alternativa k hlasovému)
        $cols = array_column($pdo->query('PRAGMA table_info(memories)')->fetchAll(), 'name');
        if (!in_array('wish_text', $cols, true)) $pdo->exec('ALTER TABLE memories ADD COLUMN wish_text TEXT');
    }
    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}

/* ---------- helpers ---------- */

function now(): string { return date('c'); }

function uuid4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function token(int $len = 10): string {
    $alpha = 'abcdefghjkmnpqrstuvwxyz23456789'; // bez zaměnitelných znaků
    $t = '';
    for ($i = 0; $i < $len; $i++) $t .= $alpha[random_int(0, strlen($alpha) - 1)];
    return $t;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function json_out($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $msg, int $code = 400): never { json_out(['error' => $msg], $code); }

function ts(?string $s): ?int { return $s ? strtotime($s) : null; }

function submissions_open(): bool {
    $c = ts(cfg('submissions_close'));
    return $c === null || time() <= $c;
}

function party_started(): bool { return time() >= (ts(cfg('party_date')) ?? PHP_INT_MAX); }

function kino_public(): bool { return time() >= (ts(cfg('kino_public_from')) ?? PHP_INT_MAX); }

/** Rozdělí "Petra a Honza", "Petra & Honza", "Petra, Honza" na jména. */
function split_names(string $label): array {
    $parts = preg_split('/\s*(?:,|&|\+|\sa\s)\s*/u', trim($label)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), fn($x) => $x !== ''));
}

/* ---------- auth ---------- */

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('p50');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 120,
        'path' => '/',
        'secure' => (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 120));
    session_save_path(data_dir('sessions'));
    session_start();
}

function current_household(): ?array {
    start_session();
    $id = $_SESSION['hid'] ?? null;
    if (!$id) return null;
    $st = db()->prepare('SELECT * FROM households WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetch() ?: null;
    if ($h) $h['names'] = json_decode($h['names_json'], true);
    return $h;
}

function login_with_token(string $tok): ?array {
    $st = db()->prepare('SELECT * FROM households WHERE token = ?');
    $st->execute([strtolower(trim($tok))]);
    $h = $st->fetch();
    if (!$h) return null;
    start_session();
    session_regenerate_id(true);
    $_SESSION['hid'] = (int)$h['id'];
    db()->prepare('UPDATE households SET last_seen = ? WHERE id = ?')->execute([now(), $h['id']]);
    return $h;
}

function is_admin(): bool {
    start_session();
    $k = (string)cfg('admin_key');
    if (isset($_GET['k']) && $k !== '' && $k !== 'ZMEN-MNE' && hash_equals($k, (string)$_GET['k'])) {
        $_SESSION['admin'] = true;
    }
    return !empty($_SESSION['admin']);
}

/** Jednoduchá ochrana API proti CSRF: vyžaduje vlastní hlavičku (posílá jen náš JS). */
function require_xhr(): void {
    if (($_SERVER['HTTP_X_P50'] ?? '') !== '1') fail('Neplatný požadavek', 403);
}

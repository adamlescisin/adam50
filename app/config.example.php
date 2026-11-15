<?php
/**
 * Konfigurace microsite. Zkopíruj jako app/config.php a uprav.
 * config.php je v .gitignore – nikdy ho necommituj (obsahuje admin klíč).
 */
return [
    // Kdo slaví
    'celebrant'      => 'Adam',
    'celebrant_vocative' => 'Adame',             // 5. pád pro finále: „Všechno nejlepší, Adame!“
    'birth_year'     => 1976,
    'party_date'     => '2026-12-12 18:00',      // Europe/Prague
    'party_place'    => 'Hostín',
    'site_title'     => 'Adamových 50',

    // Veřejná adresa (bez lomítka na konci) – používá se pro osobní odkazy a QR kódy
    'base_url'       => 'https://50.example.cz',

    // Tajný klíč administrace: /admin?k=<klíč>  (vygeneruj: php -r "echo bin2hex(random_bytes(16));")
    'admin_key'      => 'ZMEN-MNE',

    // Uzávěrka příspěvků (po ní je sběr jen pro čtení). null = bez uzávěrky.
    'submissions_close' => '2026-12-11 23:59',

    // Kdy se hosté smí podívat na "Kino" (po oslavě jako dárek). Admin ho vidí vždy.
    'kino_public_from'  => '2026-12-13 10:00',

    // Smí hosté před oslavou vidět příspěvky ostatních? false = překvapení až na oslavě.
    'wall_visible_before_party' => false,

    // Limity nahrávání
    'max_photos_per_memory' => 12,
    'max_video_mb'          => 200,
    'max_audio_mb'          => 25,

    // Hudba na pozadí Kina (soubor do public/assets/music/, vlastní licence!). null = bez hudby.
    'kino_music'     => null, // např. 'assets/music/soundtrack.mp3'

    // Kategorie (štítky) vzpomínek
    'tags' => ['Rodina', 'Škola', 'Práce', 'Cesty', 'Sport', 'Mejdan', 'Trapas', 'Jen tak'],

    // Cesta k datům (DB + média). Na Hostingeru ideálně MIMO public_html.
    'data_dir'       => __DIR__ . '/../data',
];

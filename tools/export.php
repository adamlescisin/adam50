<?php
/**
 * Export všech příspěvků do offline balíčku (stejný jako tlačítko v administraci).
 *   php tools/export.php [cilovy.zip]
 * Obsah: index.html (Kino), show.json, guests.json, media/<id>/{*.jpg, memory.json, …}
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/media.php';
$target = $argv[1] ?? __DIR__ . '/../p50-export-' . date('Ymd-His') . '.zip';
build_export_zip($target);
printf("Export: %s (%.1f MB)\nPromítání offline: unzip, cd do složky, `python3 -m http.server 8050`, otevři http://localhost:8050\n", realpath($target), filesize($target) / 1048576);

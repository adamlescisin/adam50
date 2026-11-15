<?php
/**
 * Naplní lokální instalaci ukázkovými hosty a vzpomínkami (s vygenerovanými „retro“ fotkami).
 * POUZE pro vývoj / náhled:  php tools/seed-demo.php [--reset]
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/media.php';

if (in_array('--reset', $argv, true)) {
    @unlink(data_dir() . '/party.sqlite'); @unlink(data_dir() . '/party.sqlite-wal'); @unlink(data_dir() . '/party.sqlite-shm');
    rrmdir(data_dir('media'));
}
$pdo = db();
$FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf';

function fake_photo(int $seed, int $year, bool $portrait = false): string {
    global $FONT;
    mt_srand($seed);
    [$w, $h] = $portrait ? [1067, 1600] : [1600, 1067];
    $im = imagecreatetruecolor($w, $h);
    $palettes = [
        [[255, 183, 94], [233, 98, 76], [74, 46, 72]],    // západ slunce
        [[150, 205, 230], [80, 140, 190], [30, 60, 90]],  // moře
        [[210, 230, 240], [120, 160, 150], [40, 70, 60]], // hory
        [[40, 30, 60], [90, 50, 110], [230, 150, 80]],    // večírek
        [[250, 220, 160], [190, 140, 90], [90, 60, 40]],  // léto
    ];
    $p = $palettes[$seed % count($palettes)];
    for ($y = 0; $y < $h; $y++) { // gradient oblohy
        $t = $y / $h; $a = $t < .5 ? $p[0] : $p[1]; $b = $t < .5 ? $p[1] : $p[2]; $k = $t < .5 ? $t * 2 : ($t - .5) * 2;
        $c = imagecolorallocate($im, (int)($a[0] + ($b[0] - $a[0]) * $k), (int)($a[1] + ($b[1] - $a[1]) * $k), (int)($a[2] + ($b[2] - $a[2]) * $k));
        imageline($im, 0, $y, $w, $y, $c);
    }
    imagefilledellipse($im, mt_rand((int)($w * .2), (int)($w * .8)), (int)($h * mt_rand(25, 45) / 100), 180, 180, imagecolorallocatealpha($im, 255, 240, 200, 30));
    for ($layer = 0; $layer < 3; $layer++) { // kopce
        $col = imagecolorallocatealpha($im, (int)($p[2][0] * (1 - $layer * .25)), (int)($p[2][1] * (1 - $layer * .25)), (int)($p[2][2] * (1 - $layer * .25)), 20 - $layer * 8);
        $pts = [0, $h]; $base = $h * (.55 + $layer * .13);
        for ($x = 0; $x <= $w; $x += 80) { $pts[] = $x; $pts[] = (int)($base + sin($x / (140 + $layer * 60) + $seed) * 50 + mt_rand(-15, 15)); }
        $pts[] = $w; $pts[] = $h;
        imagefilledpolygon($im, $pts, $col);
    }
    if ($seed % 5 === 3) for ($i = 0; $i < 40; $i++) { // bokeh světýlka
        imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, (int)($h * .7)), $r = mt_rand(20, 90), $r, imagecolorallocatealpha($im, 255, mt_rand(150, 230), mt_rand(80, 160), mt_rand(70, 110)));
    }
    // vybledlé barvy podle stáří
    $age = max(0, 2026 - $year);
    if ($age > 15) { imagefilter($im, IMG_FILTER_COLORIZE, 30, 15, -10); imagefilter($im, IMG_FILTER_CONTRAST, 12); }
    if ($age > 30) imagefilter($im, IMG_FILTER_BRIGHTNESS, 10);
    for ($i = 0; $i < 3500; $i++) imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocatealpha($im, 255, 255, 255, 100)); // zrno
    // datumovka jako ze staré kompaktu
    if (is_file($FONT)) imagettftext($im, 34, 0, $w - 330, $h - 50, imagecolorallocate($im, 255, 140, 40), $FONT, sprintf("'%02d %d %d", $year % 100, mt_rand(1, 12), mt_rand(1, 28)));
    $f = tempnam(sys_get_temp_dir(), 'p50') . '.jpg';
    imagejpeg($im, $f, 88);
    return $f;
}

$households = [
    ['Petra a Honza', 'spolužáci z gymplu'],
    ['Martin', 'kolega z Accenture'],
    ['Jana a Pavel', 'sousedé z Hostína'],
    ['Tomáš', 'kamarád od dětství'],
    ['Lucie a Ondřej', 'parta z vody'],
    ['Klára', 'kolegyně z banky'],
    ['Radek a Eva', 'volejbalová parta'],
    ['Michal', 'bratranec'],
];
$hid = [];
foreach ($households as [$label, $rel]) {
    $pdo->prepare('INSERT INTO households (token, label, names_json, relation, created_at, last_seen) VALUES (?,?,?,?,?,?)')
        ->execute([$label === 'Petra a Honza' ? 'demo123456' : token(), $label, json_encode(split_names($label), JSON_UNESCAPED_UNICODE), $rel, now(), now()]);
    $hid[$label] = (int)$pdo->lastInsertId();
}

$memories = [
    ['Tomáš', ['Tomáš'], 1984, 'Kolo bez brzd', "Adam si v osmi letech postavil kolo ze tří různých kol. Brzdy nemělo, ale prý to nevadí, protože „se dá brzdit nohama“. Dolů z kopce u hřiště jsme to zjistili oba. Kolo přežilo, my taky – jenom máma ne.", ['Sport', 'Trapas'], 2, false],
    ['Michal', ['Michal'], 1988, 'Prázdniny u babičky', 'Každé léto jsme spolu trávili u babičky na chalupě. Adam vždycky vstával první a budil mě s tím, že „ryby nečekají“. Nechytili jsme nikdy nic.', ['Rodina'], 3, false],
    ['Petra a Honza', ['Petra', 'Honza'], 1993, 'Maturitní ples', "Na maturiťáku Adam tančil s třídní tak dlouho, že kapela musela přidat. Pak nás všechny vzal na noční vlak do Prahy, protože „ráno se musí vidět Karlův most bez lidí“. Bylo to nejlepší rozhodnutí toho roku.", ['Škola', 'Mejdan'], 2, false],
    ['Lucie a Ondřej', ['Lucie', 'Ondřej'], 1998, 'Vltava 98', 'Tři dny lilo, kanoe jsme převrátili dvakrát a Adam stejně tvrdil, že to je nejlepší vodácký výlet v historii. Měl pravdu.', ['Cesty', 'Sport'], 4, false],
    ['Klára', ['Klára'], 2007, null, "S Adamem jsme spolu tři měsíce zachraňovali projekt, který všichni odepsali. Jednou ve dvě ráno přinesl do kanceláře guláš od své mámy a řekl: „S plným břichem se to zvládne.“ Zvládlo. Dodnes nevím, jestli víc pomohl ten guláš, nebo on.", ['Práce'], 0, false],
    ['Martin', ['Martin'], 2016, 'Workshop, na který se nezapomíná', 'Klient chtěl operační model „do pátku“. Adam nakreslil na tabuli celou banku jedním fixem a pak se otočil a řekl: „Tak, a teď to rozbijeme.“ Od té doby tak začínám každý workshop.', ['Práce'], 1, false],
    ['Radek a Eva', ['Radek', 'Eva'], 2022, 'Středeční volejbal', null, ['Sport', 'Jen tak'], 3, false],
    ['Jana a Pavel', ['Jana', 'Pavel'], 2024, 'Soused, jakého si přejete', 'Když nám v zimě zamrzla voda, Adam přišel s horkovzdušnou pistolí, termoskou čaje a pěti nápady. Čtyři nefungovaly. Ten pátý ano.', ['Rodina', 'Jen tak'], 1, false],
    ['Petra a Honza', ['Honza'], null, null, "Adam je člověk, kterému zavoláš ve tři ráno a on se jen zeptá: „Kam mám přijet?“", [], 0, false],
    ['Lucie a Ondřej', ['Lucie', 'Ondřej'], null, 'Do dalších padesáti', 'Ať tě nikdy nepřestane bavit vymýšlet blbosti, které nakonec fungují. A ať je na vodě pořád dost vody. Všechno nejlepší!', [], 1, true],
    ['Martin', ['Martin'], null, null, 'Přeju ti, aby dalších 50 let bylo míň o operačních modelech a víc o kempování s výhledem. 🏕️', [], 0, true],
];

$seed = 1;
foreach ($memories as [$hh, $authors, $year, $title, $body, $tags, $nPhotos, $wish]) {
    $id = uuid4();
    $created = date('c', time() - mt_rand(1, 20) * 86400);
    $pdo->prepare('INSERT INTO memories (id, household_id, authors_json, title, body, year, tags_json, is_wish, wish_text, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$id, $hid[$hh], json_encode($authors, JSON_UNESCAPED_UNICODE), $wish ? $title : $title, $wish ? null : $body, $year, json_encode($tags, JSON_UNESCAPED_UNICODE), 1, $wish ? $body : null, $created, $created]);
    for ($i = 0; $i < $nPhotos; $i++) {
        $tmp = fake_photo($seed++, $year ?? 2020, $i === 1);
        $r = store_upload($id, ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'x.jpg'], 'auto', $i);
        $pdo->prepare('INSERT INTO media (memory_id, kind, file, thumb, mime, width, height, bytes, sort, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $r['kind'], $r['file'], $r['thumb'], $r['mime'], $r['width'], $r['height'], $r['bytes'], $i, now()]);
        @unlink($tmp);
    }
    write_sidecar($id);
}
echo "Hotovo: " . count($households) . " domácností, " . count($memories) . " vzpomínek.\nTestovací host: /g/demo123456\n";

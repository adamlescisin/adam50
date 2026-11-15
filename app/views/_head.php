<?php
/** @var string $BASE  @var string $pageTitle  @var string $bodyClass */
$assets = ($offline ?? false) ? 'assets' : (($BASE ?? '') . '/assets');
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#14110e">
<title><?= e($pageTitle ?? cfg('site_title')) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%2314110e'/><text x='32' y='44' font-family='Georgia' font-style='italic' font-size='34' text-anchor='middle' fill='%23e3a94b'>50</text></svg>">
<link rel="preload" href="<?= $assets ?>/fonts/fraunces-latin-ext-standard-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $assets ?>/css/app.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/public/assets/css/app.css') ?: 1 ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="grain" aria-hidden="true"></div>

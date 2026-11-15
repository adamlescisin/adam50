# Adamových 50 🎬

Private birthday microsite (in Czech). Guests share stories, photos and voice messages, and on the party day it all plays as an auto-generated interactive film.

**Stack:** PHP 8.1+ · SQLite · vanilla JS · no build step · self-hosted fonts. Details are in [ARCHITECTURE.md](ARCHITECTURE.md).

## Quick start (local)

```bash
cp app/config.example.php app/config.php     # set admin_key, base_url, dates
php tools/seed-demo.php --reset              # optional sample data (test guest: /g/demo123456)
php -S localhost:8050 -t public public/index.php
```

- Guest view: http://localhost:8050/g/demo123456
- Admin: http://localhost:8050/admin?k=YOUR_ADMIN_KEY
- Kino: http://localhost:8050/kino (admin always; guests after `kino_public_from`)

## Party day

```bash
php tools/export.php party.zip      # or Admin → "Export pro oslavu"
unzip party.zip -d party && cd party && python3 -m http.server 8050
# Chrome → http://localhost:8050 → F (fullscreen) → ▶
```

Keys: `Space` pause · `←/→` · `G` overview · `R` random · `M` music · `Enter` reveal author (guess game).

## Standalone design preview

`php tools/build-demo.php` builds `demo/dist/index.html`: one clickable file with a mocked API and the Kino, using the seeded data.

## Requirements on hosting

PHP extensions `gd` (JPEG), `pdo_sqlite`, `fileinfo`, `zip`, `exif` (optional). Apache or LiteSpeed with `.htaccess`, or Docker (`docker compose up -d`).

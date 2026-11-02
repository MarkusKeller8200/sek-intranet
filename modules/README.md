# Module

Fachthemen des Intranets mit eigenen Rechten, eigenem Menü, eigenen Seiten und eigener Datenhaltung
(Konzept und Regeln: [docs/KONZEPT-MODULE.md](../docs/KONZEPT-MODULE.md)).

```bash
php artisan intranet:make-module Zeiterfassung --gruppe=organisation   # Gerüst anlegen
composer dump-autoload && composer ci:check
php artisan intranet:module-sql zeiterfassung                           # SQL für Schema und DB-Benutzer (später)
```

Ein- und ausschalten unter **Einstellungen → Module**.

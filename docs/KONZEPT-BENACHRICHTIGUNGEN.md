# Konzept Benachrichtigungen (ab 0.31.0)

Das Intranet meldet Personen, wenn etwas für sie ansteht – zuerst bei den GUI-Prototypen, später auch bei
Tickets, Bestellungen, Abgaben oder Plugins.

## Bedienung

- **Glocke** oben rechts in jeder Seite: Zahl der ungelesenen Meldungen, die acht neuesten, «Alle gelesen»,
  «Alle anzeigen».
- **Seite** `/benachrichtigungen`: alle Meldungen (neueste zuerst), ungelesene hervorgehoben.
- Klick auf eine Meldung: gilt als gelesen und öffnet die passende Stelle im Intranet.
- **Ohne Neuladen:** Die Glocke fragt alle 45 Sekunden nach (nur wenn die Seite sichtbar ist). Neue Meldungen
  erscheinen zusätzlich unten als kurze Meldung mit «Öffnen».

## Regeln

- Wer etwas auslöst, wird nicht selbst benachrichtigt.
- Empfänger:innen nach **Recht** (`Notifications::toAbility`, z. B. `review-gui`) oder einzeln
  (`Notifications::toUsers`, z. B. Autor:in eines Wunsches) – nie nach Rolle im Code.
- Titel und Text kurz und ohne Personendaten von Schüler:innen; der Link zeigt nur auf Seiten im Intranet.
- Je Person bleiben höchstens 200 gelesene Meldungen; ältere gelesene werden automatisch entfernt.

## Meldungen heute

| Art (`kind`)       | Auslöser                          | Empfänger:innen                          |
| ------------------ | --------------------------------- | ---------------------------------------- |
| `gui.review`       | Version zur Prüfung gegeben       | `review-gui`                             |
| `gui.returned`     | Prüfung: zurück an Entwicklung    | Ersteller:in der Version, Administration |
| `gui.released`     | Version freigegeben               | `use-gui`                                |
| `gui.wish`         | neuer Wunsch                      | Administration                           |
| `gui.wish.planned` | Wunsch in neue Version übernommen | Autor:in des Wunsches                    |

## Technik

- Sammlung `notifications` (user_id, kind, title, body, href, created_by, read_at, sent_at),
  `App\Support\Notifications`, `NotificationController`.
- Geteilte Inertia-Daten `auth.notifications` (ungelesen, neueste), Abfrage `GET /benachrichtigungen/stand`.
- Glocke: `resources/js/components/notification-bell.tsx` im Seitenkopf (`app-sidebar-header.tsx`).

## Später

- E-Mail (Zusammenfassung täglich) und Push über die PWA, je Person abschaltbar.
- Weitere Auslöser: Tickets, Bestellungen, Geräterücknahme, Plugin-Fehler, Abgleich nötig (Datenherkunft).

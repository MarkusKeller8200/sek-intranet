# Konzept ClickTime

Plugin `plugins/Clicktime` (Meilenstein «ClickTime – Integration», #27). Quelle: OpenAPI-Export der Postman-Sammlung
von dev.clicktime.ch (05.11.2026), 51 Endpunkte; dazu `/approvals` (angekündigt 27.11.2026).

## Grundsätze

- Die **eigene Zeiterfassung** (Modul Zeiterfassung) funktioniert ohne ClickTime. Das Plugin ergänzt sie; ob ClickTime
  nur Daten liefert (Variante A) oder parallel läuft (Variante C), entscheidet sich aus den Möglichkeiten der API (#27).
- **Anmeldung:** OAuth 2 Client Credentials, Token 1 Stunde (zwischengespeichert). Basis
  `https://login2.clicktime.ch/api/v3/customers/{customer}`, Token `https://login.clicktime.ch/auth/realms/openapi/…`.
  client_id und client_secret verschlüsselt (Einstellungen), im Live-System von ClickTime erstellt.
- **Filter:** `filters=feld::OP::wert` mehrfach (OP: EQ, IN, LIKE, GE, LE). Buchungen höchstens ein Monat pro Abfrage.
- **Datenschutz:** Endpunkte mit Personendaten (`personal_data`) gehen nur vom Schweizer Server an ClickTime. Auf dem
  Testserver Demo-Modus; mit «Endtest» nur Abteilungen, Feiertage, Projekte, Kunden.
- **Abgleich:** Datenherkunft System `clicktime`, ID = `employee_no`; sonst über die E-Mail. Es wird nichts angelegt oder
  gelöscht.

## Monatsdaten und Abschluss (#32, #97)

- **Monatsblatt:** `entries` (Monat), `cumulatives` (Monat), `balances` (Monatsende), `public-holiday-calendars`
  (Jahr). Vollständigkeit: Mo–Fr ohne Buchung, Feiertage ausgenommen. PDF mit `SimplePdf`, Ablage in «Dokumente &
  Links» (Kategorie `monatsblaetter`, Recht `manage-signatures`, Herkunft `clicktime:monatsblatt:<Monat>:<Nr.>`).
- **Abschluss:** im Intranet (ClickTime hat keinen Abschluss-Endpunkt). Stand je Person ohne Notizen unter
  `storage/app/private/plugins/clicktime/abschluss/<JJJJ-MM>.json`. Vergleich über die Buchungsnummer (Felder Tag,
  Zeit, Stunden, Art, Tätigkeit). Hinweis an Recht `manage-signatures` und die Vorgesetzten (über die Datenherkunft).
- Später möglich: Abschluss automatisch beim Visum der Zeiterfassung, regelmässige Prüfung.

## Abgleich mit der Zeiterfassung (#99, #100)

- Echte Antworten (05.11.2026): Buchungen mit `entry_type_name` {de, en}, `entry_type_number` meist leer,
  `entry_type_type` (WORK, HOLIDAYS, COMPENSATION …), Pause als Buchung mit negativen Stunden, gelöschte Buchungen mit
  `deleted: true`; Kumulative je Person unter `cumulative_values`.
- Zeiterfassung: eigene Buchungsarten (Modul, Sammlung `zeit_buchungsarten`), ohne Bezug zu ClickTime.
- Plugin: Mapping-Tabelle (Einstellung `mapping`, «Name = Schlüssel», `pause`, `-`), Vorschlag über gleichen Namen
  bzw. Kategorie. Vergleich je Tag und Buchungsart; Pause wird von der grössten Arbeit-Buchungsart des Tages abgezogen.
- Import (#101): `Plugins\Clicktime\Importer` – Einträge mit `quelle = clicktime`, `quelle_id` = ClickTime-Nr.;
  neu/geändert/gelöscht nur für übernommene Einträge, von Hand erfasste bleiben. Unbekannte Buchungsarten →
  `ct_<name>` «Name (clicktime)», Mapping ergänzt. Kategorie bei Arbeit `ct:<Name>`. Pause in den längsten
  Arbeitsblock. Nur offene/zurückgegebene Monate. Knopf in der Zeiterfassung für die Administration, Mitarbeitende
  mit Einstellung `import_self`; später Zeitplan möglich.
- Offen: Export Zeiterfassung → ClickTime.

## Genehmigungen (#228, #200)

- Vorschlag ClickTime (27.11.2026): Buchungen über `/entries`, Genehmigungen über `/approvals`, Abgleich über die
  Eintragsnummer. Felder je Zeiteintrag: `number` (wie in `/entries`), `day`, `entry_type_number`, `approved_1_by`
  (Personalnummer der/des Vorgesetzten), `approved_1_at`, `deleted` (Genehmigung zurückgenommen), `last_update`.
- Umsetzung: `Months::entries()` ruft nach den Buchungen `approvals` für denselben Monat ab (`day::GE`/`day::LE`) und
  setzt je Buchung `approval` («Genehmigt»/«Offen»), `approved_by`, `approved_at`. Mehrere Datensätze je Nummer: der
  mit der jüngsten `last_update` gilt. Antwortet `/approvals` nicht (noch nicht freigeschaltet, Scope fehlt), bleibt
  `approval` leer – Buchungen, Abschluss und Import funktionieren unverändert.
- Anzeige: Monatsblatt (eine Person: «Genehmigt – Name, Datum»; alle: «genehmigt / Buchungen»), Zeiterfassung über
  `quelle_status`. Der Monatsabschluss vergleicht die Genehmigung nicht (nur die Buchungsfelder).
- Zu prüfen beim ersten echten Aufruf: Filter von `/approvals`, Name des Scopes, Format von `approved_1_at`, ob
  `deleted` als eigener Datensatz oder als Zustand desselben Datensatzes kommt (beides wird verarbeitet).

## Offen (#28)

- Antwortfelder aller Endpunkte (die Beschreibung enthält keine Antworten) – Demo-Daten sind geraten, nach dem ersten
  echten Aufruf auf dem Schweizer Server anpassen.
- Body von `POST/PUT employees`, Felder von `employments` (Arbeitsplan, Pensum), Bedeutung der Buchungsarten
  (`entry_type_number`), Feldname `entry_number`/`entry_type_number`, Pfad von `PUT entities/{n}/categories`.
- Scopes des Clients, Rate-Limits, Serverstandort/IDG.

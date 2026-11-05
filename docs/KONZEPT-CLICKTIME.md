# Konzept ClickTime

Plugin `plugins/Clicktime` (Meilenstein «ClickTime – Integration», #27). Quelle: OpenAPI-Export der Postman-Sammlung
von dev.clicktime.ch (05.11.2026), 51 Endpunkte.

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

## Offen (#28)

- Antwortfelder aller Endpunkte (die Beschreibung enthält keine Antworten) – Demo-Daten sind geraten, nach dem ersten
  echten Aufruf auf dem Schweizer Server anpassen.
- Body von `POST/PUT employees`, Felder von `employments` (Arbeitsplan, Pensum), Bedeutung der Buchungsarten
  (`entry_type_number`), Feldname `entry_number`/`entry_type_number`, Pfad von `PUT entities/{n}/categories`.
- Scopes des Clients, Rate-Limits, Serverstandort/IDG.

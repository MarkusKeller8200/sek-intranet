# Rollenkonzept

Stand: Version 0.14.0. Quelle der Wahrheit: `config/intranet.php` → `roles`, `role_descriptions`, `abilities`.
Übersicht im Intranet: Prototyp → Rollenkonzept (Matrix und sichtbare Menüs je Rolle, live berechnet).

## Grundsätze

1. Menüs, Routen und Seiten fragen **Rechte** ab (Gates wie `use-ict`), nie Rollen. Eine neue oder geänderte
   Rolle braucht nur eine Änderung der Zuordnung unter `abilities`.
2. Im Prototyp hat jede Person **eine** Rolle (Feld «Rolle im Intranet»). Rollen mit mehr Aufgaben enthalten die
   Rechte der einfacheren (z. B. Fachstelle ICT auch Unterricht).
3. Produktiv kommen die Rollen aus **Gruppen in Entra ID** (Anmeldung über OIDC, IDA ADR). Eine Person kann dann
   mehrere Rollen haben; ihre Rechte werden zusammengezählt.
4. So wenig Administration wie möglich.
5. Feldschutz bleibt unabhängig von der Rolle: private Angaben, Notfallkontakte und Systemfelder sieht nur die
   Administration.

## Rollen

| Rolle           | Schlüssel       | Für wen                                                           |
| --------------- | --------------- | ----------------------------------------------------------------- |
| Administration  | `admin`         | Betrieb des Intranets: Personen, Plugins, Einstellungen, Prototyp |
| Schulleitung    | `schulleitung`  | Schulleitungen                                                    |
| Schulverwaltung | `verwaltung`    | Sekretariat, Schulverwaltung                                      |
| Fachstelle ICT  | `ict`           | ICT-Support, pädagogischer ICT-Support                            |
| Redaktion       | `redaktion`     | Pflege von Mitteilungen, Terminen, Dokumenten                     |
| Lehrperson      | `lehrperson`    | Klassen- und Fachlehrpersonen, SHP, DaZ                           |
| Mitarbeitende   | `mitarbeitende` | Hausdienst, Betreuung, Assistenz                                  |

## Rechte

| Recht            | Bedeutung                                   | Admin | Schulleitung | Verwaltung | ICT | Redaktion | Lehrperson | Mitarbeitende |
| ---------------- | ------------------------------------------- | :---: | :----------: | :--------: | :-: | :-------: | :--------: | :-----------: |
| `manage-users`   | Personen und Intranet verwalten, Prototyp   |   ✓   |              |            |     |           |            |               |
| `manage-content` | Mitteilungen, Termine, Dokumente bearbeiten |   ✓   |      ✓       |            |     |     ✓     |            |               |
| `use-ict`        | Fachstelle ICT (Geräteausgabe, Lizenzen …)  |   ✓   |              |            |  ✓  |           |            |               |
| `use-verwaltung` | Verwaltung (Workflows)                      |   ✓   |      ✓       |     ✓      |     |           |            |               |
| `use-unterricht` | Unterricht (Klassen, Stundenpläne …)        |   ✓   |      ✓       |            |  ✓  |           |     ✓      |               |
| `order`          | Bestellen (Ausrüstung, Lehrmittel …)        |   ✓   |      ✓       |     ✓      |  ✓  |           |     ✓      |               |

Lesen (Start, Mitteilungen, Termine, Dokumente, Personalverzeichnis ohne Admin-Felder, Wissen, Tickets,
Elternportal) dürfen alle angemeldeten Personen.

## Umsetzung

- `App\Support\Roles` liest Rollen und Rechte; `IntranetServiceProvider` legt je Recht ein Gate an.
- Menüs: `modules.<bereich>.can` bzw. `modules.<bereich>.items.<modul>.can` mit einem Recht.
- Routen: `->middleware('can:<recht>')`.
- `RoleTest` prüft, dass jedes Recht nur bekannte Rollen nennt, die Administration alles darf und jedes in der
  Navigation verwendete Recht definiert ist.
- Demo-Personen: Beat Beispiel (Schulleitung), Gabriela Vorlage (Verwaltung), Daniel Probe und Eva Fiktiv
  (Lehrperson), Fabian Platzhalter (Fachstelle ICT). `intranet:upgrade` stellt bestehende Demo-Daten einmalig um.

## Später (produktiv)

- Entra-ID-Gruppen je Rolle, z. B. `SG-Intranet-ICT`, `SG-Intranet-Verwaltung`; Zuordnung Gruppe → Rolle in der
  Konfiguration, Abgleich bei jeder Anmeldung.
- Mehrere Rollen je Person (Feld wird zur Liste), Rechte als Vereinigung.
- Schulhaus-bezogene Rechte (z. B. Schulleitung nur für das eigene Schulhaus), falls nötig.

# Konzept MCP-Schnittstelle

Stand: Version 0.11.0, nur Konzept – der Endpunkt `/mcp` ist noch nicht umgesetzt. Seite im Intranet:
Prototyp → MCP-Schnittstelle (Werkzeugliste live aus der API-Beschreibung).

## Ziel

Das Intranet wird ein MCP-Server (Model Context Protocol). KI-Assistenten – Claude, ChatGPT, Atlassian Rovo –
können damit Inhalte nachschlagen und später, mit Bestätigung, Daten ändern. Es gelten immer die Rechte der
angemeldeten Person.

## Aufbau

1. **KI-Client** (Claude, ChatGPT oder Rovo). Die Person meldet sich einmal mit ihrem Intranet-Konto an.
2. **Endpunkt `/mcp`**: Transport Streamable HTTP, JSON-RPC 2.0. Umsetzung mit dem Laravel-Paket `laravel/mcp`,
   Anmeldung OAuth 2.1 über Laravel Passport (`Mcp::oauthRoutes()` stellt Discovery unter
   `/.well-known/oauth-protected-resource` und `/.well-known/oauth-authorization-server` sowie die dynamische
   Client-Registrierung nach RFC 7591 bereit).
3. **Gleiche Regeln wie die API** (`/api/v1`): Werkzeuge rufen dieselben Policies und Prüfungen auf.
4. **Daten und Protokoll**: Änderungen im Änderungsverlauf mit Quelle «MCP» und Client-Name.

## Clients

| Client  | Einbindung                                                                                | Anmeldung                                        |
| ------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Claude  | Benutzerdefinierter Connector (Remote-MCP); in Team/Enterprise durch Inhaber für alle     | OAuth (mit dynamischer Registrierung) oder keine |
| ChatGPT | Entwicklermodus (Beta, Web; Plus, Pro, Business, Enterprise, Edu); nur Remote über HTTPS  | OAuth oder keine; Schreibaktionen mit Rückfrage  |
| Rovo    | Atlassian-Administration → Verbundene Apps → eigenen MCP-Server hinzufügen                | OAuth 2.1 mit PKCE und DCR (Pflicht)             |

Alle drei verlangen einen öffentlich erreichbaren HTTPS-Server. OAuth 2.1 mit PKCE und dynamischer
Client-Registrierung deckt alle drei ab.

## Werkzeuge

Abgeleitet aus `App\Support\OpenApi` durch `App\Support\McpConcept` (eine neue API-Operation erscheint
automatisch):

| Werkzeug             | API                    | Art       | Phase |
| -------------------- | ---------------------- | --------- | ----- |
| `whoami`             | GET /me                | lesen     | 1     |
| `list_announcements` | GET /announcements     | lesen     | 1     |
| `list_events`        | GET /events            | lesen     | 1     |
| `list_documents`     | GET /documents         | lesen     | 1     |
| `get_options`        | GET /options           | lesen     | 1     |
| `search_people`      | GET /people            | lesen     | 2     |
| `get_person`         | GET /people/{id}       | lesen     | 2     |
| `create_person`      | POST /people           | schreiben | 3     |
| `update_person`      | PATCH /people/{id}     | schreiben | 3     |
| `delete_person`      | DELETE /people/{id}    | löschen   | 4     |

Jedes Werkzeug erhält MCP-Annotations (`readOnlyHint`, `destructiveHint`, `idempotentHint`,
`openWorldHint=false`). Sie sind Hinweise an den Client, keine Sicherheitsgrenze.

## Datenschutz (IDG Kanton Zürich)

- Antworten gehen an den Anbieter des KI-Clients (USA bzw. Atlassian-Cloud). Ohne Vertrag zur
  Auftragsbearbeitung keine Personendaten freigeben.
- Phase 1 nur Inhalte, die ohnehin für alle Mitarbeitenden sichtbar sind.
- Private Felder, Notfallkontakte und Admin-Felder nie über MCP – auch nicht für die Administration.
- Betrieb nur auf einem Server in der Schweiz (Infomaniak, ADR-005), nicht auf dem Prototyp-Server.
- Tokens laufen ab, sind je Client widerrufbar und nur als Hash gespeichert.

## Phasen

1. Lesen: Inhalte (Pilot mit Claude in der Schulleitung)
2. Lesen: Personalverzeichnis, nur geschäftliche Kontaktdaten, nach Freigabe Datenschutz
3. Schreiben mit Bestätigung (nur Administration)
4. Löschen – vorerst nicht vorgesehen

## Offene Fragen

- Gibt es für Claude, ChatGPT oder Rovo einen Vertrag, der Personendaten der Volksschule abdeckt?
- Welche Clients werden zugelassen?
- Sollen Schülerinnen und Schüler überhaupt Zugriff erhalten? (Empfehlung: nein)
- Wer gibt neue Werkzeuge frei – Fachstelle ICT oder Schulleitung?

## Quellen

- Atlassian: Custom MCP-Server in Rovo (Atlassian Support)
- OpenAI: Developer Mode / Connectors in ChatGPT (OpenAI Help Center)
- Anthropic: Custom Connectors mit Remote-MCP (Claude Help Center)
- Laravel: «Laravel MCP Server: Auth & Security Best Practices» (laravel.com/blog)

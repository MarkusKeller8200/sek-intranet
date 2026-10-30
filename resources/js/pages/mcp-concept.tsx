import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tool = {
    name: string;
    method: string;
    path: string;
    summary: string;
    tag: string;
    kind: string;
    phase: number;
    annotations: Record<string, boolean>;
    parameters: string[];
};

const clients = [
    {
        name: 'Claude',
        vendor: 'Anthropic',
        how: 'Benutzerdefinierter Connector (Remote-MCP) in Claude, Claude Desktop und Claude Code. In Team und Enterprise fügen Inhaber den Connector für die ganze Organisation hinzu.',
        auth: 'OAuth (mit dynamischer Client-Registrierung) oder ohne Anmeldung',
    },
    {
        name: 'ChatGPT',
        vendor: 'OpenAI',
        how: 'Entwicklermodus (Beta) im Web für Plus, Pro, Business, Enterprise und Edu. Nur entfernte Server über HTTPS; schreibende Aktionen fragen vor dem Ausführen nach.',
        auth: 'OAuth oder ohne Anmeldung',
    },
    {
        name: 'Rovo',
        vendor: 'Atlassian',
        how: 'Atlassian-Administration → Verbundene Apps → eigenen MCP-Server hinzufügen. Danach in Rovo Chat und Rovo-Agenten nutzbar.',
        auth: 'OAuth 2.1 mit PKCE und dynamischer Client-Registrierung (Pflicht), Transport Streamable HTTP',
    },
];

const layers = [
    {
        title: 'KI-Client',
        text: 'Claude, ChatGPT oder Rovo. Die Person meldet sich einmal mit ihrem Intranet-Konto an.',
    },
    {
        title: 'MCP-Endpunkt /mcp',
        text: 'Streamable HTTP, JSON-RPC 2.0. Laravel-Paket laravel/mcp, Anmeldung über OAuth 2.1 (Laravel Passport, Mcp::oauthRoutes).',
    },
    {
        title: 'Gleiche Regeln wie die API',
        text: 'Werkzeuge rufen dieselben Policies und Prüfungen auf wie /api/v1. Rechte der Person gelten, nicht die des Clients.',
    },
    {
        title: 'Daten und Protokoll',
        text: 'JSON-Ablage (später PostgreSQL). Jede Änderung im Änderungsverlauf mit Quelle «MCP» und Client-Name.',
    },
];

const privacy = [
    'Antworten eines Werkzeugs gehen an den Anbieter des KI-Clients (USA bzw. Atlassian-Cloud). Ohne Vertrag zur Auftragsbearbeitung nach IDG Kanton Zürich keine Personendaten freigeben.',
    'Phase 1 gibt nur Inhalte frei, die ohnehin für alle Mitarbeitenden sichtbar sind: Mitteilungen, Termine, Dokumente, Auswahllisten.',
    'Private Felder, Notfallkontakte und Admin-Felder werden über MCP nie ausgeliefert – auch nicht für die Administration.',
    'Schreibende Werkzeuge tragen die Annotation destructiveHint bzw. readOnlyHint=false, damit der Client nachfragt. Die Prüfung im Intranet bleibt massgebend.',
    'Betrieb nur auf einem Server in der Schweiz (Infomaniak, ADR-005). Der heutige Prototyp-Server ist dafür nicht vorgesehen.',
    'Tokens laufen ab, sind je Client widerrufbar und nur als Hash gespeichert (wie die API-Tokens).',
];

const phases = [
    {
        phase: 1,
        title: 'Lesen: Inhalte',
        text: 'Mitteilungen, Termine, Dokumente, Auswahllisten. Pilot mit Claude in der Schulleitung.',
    },
    {
        phase: 2,
        title: 'Lesen: Personalverzeichnis',
        text: 'Suche nach Name, Funktion, Schulhaus; nur geschäftliche Kontaktdaten. Nach Freigabe durch die Datenschutzbeauftragte.',
    },
    {
        phase: 3,
        title: 'Schreiben mit Bestätigung',
        text: 'Personen anlegen und ändern nur für die Administration, jede Änderung bestätigt der Client vorher.',
    },
    {
        phase: 4,
        title: 'Löschen',
        text: 'Vorerst nicht vorgesehen. Löschen bleibt im Intranet selbst.',
    },
];

const questions = [
    'Gibt es für Claude, ChatGPT oder Rovo einen Vertrag, der Personendaten der Volksschule abdeckt?',
    'Welche Clients werden zugelassen? Rovo passt, wenn die Schule bereits Confluence und Jira nutzt.',
    'Sollen Schülerinnen und Schüler überhaupt Zugriff erhalten? (Empfehlung: nein)',
    'Wer gibt neue Werkzeuge frei – Fachstelle ICT oder Schulleitung?',
];

const kindStyle: Record<string, string> = {
    lesen: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    schreiben:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    löschen: 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

export default function McpConcept({
    tools,
    endpoint,
}: {
    tools: Tool[];
    endpoint: string;
}) {
    return (
        <>
            <Head title="MCP-Schnittstelle" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="MCP-Schnittstelle"
                    description="Konzept: Das Intranet als MCP-Server, damit Claude, ChatGPT und Rovo mit den Rechten der angemeldeten Person darauf zugreifen."
                />

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-3 text-sm">
                        <Badge variant="outline">Konzept</Badge>
                        <span>Geplanter Endpunkt</span>
                        <code
                            className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                            translate="no"
                        >
                            {endpoint}
                        </code>
                        <span className="text-muted-foreground">
                            noch nicht aktiv
                        </span>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Aufbau</CardTitle>
                        <CardDescription>
                            Das Model Context Protocol (MCP) ist ein offener
                            Standard, über den KI-Assistenten Werkzeuge und
                            Daten anderer Systeme nutzen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid gap-3 md:grid-cols-4">
                            {layers.map((layer, i) => (
                                <li
                                    key={layer.title}
                                    className="relative rounded-lg border bg-muted/40 p-3 text-sm"
                                >
                                    <div className="mb-1 flex items-center gap-2 font-semibold">
                                        <span className="flex size-5 items-center justify-center rounded-full bg-primary text-[11px] text-primary-foreground">
                                            {i + 1}
                                        </span>
                                        {layer.title}
                                    </div>
                                    <p className="text-muted-foreground">
                                        {layer.text}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Clients</CardTitle>
                        <CardDescription>
                            Alle drei verlangen einen öffentlich erreichbaren
                            Server mit HTTPS. Mit OAuth 2.1, PKCE und
                            dynamischer Client-Registrierung sind alle drei
                            abgedeckt.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b">
                                    <th className="py-2 pr-4 font-medium">
                                        Client
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Einbindung
                                    </th>
                                    <th className="py-2 font-medium">
                                        Anmeldung
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => (
                                    <tr
                                        key={client.name}
                                        className="border-b align-top last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            <div
                                                className="font-medium"
                                                translate="no"
                                            >
                                                {client.name}
                                            </div>
                                            <div
                                                className="text-xs text-muted-foreground"
                                                translate="no"
                                            >
                                                {client.vendor}
                                            </div>
                                        </td>
                                        <td className="py-2 pr-4">
                                            {client.how}
                                        </td>
                                        <td className="py-2 text-muted-foreground">
                                            {client.auth}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Werkzeuge</CardTitle>
                        <CardDescription>
                            Abgeleitet aus der OpenAPI-Beschreibung der API –
                            eine neue API-Operation erscheint hier automatisch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b">
                                    <th className="py-2 pr-4 font-medium">
                                        Werkzeug
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Beschreibung
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Art
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Phase
                                    </th>
                                    <th className="py-2 font-medium">API</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tools.map((tool) => (
                                    <tr
                                        key={tool.name}
                                        className="border-b align-top last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            <code
                                                className="font-mono text-xs font-semibold"
                                                translate="no"
                                            >
                                                {tool.name}
                                            </code>
                                            {tool.parameters.length > 0 && (
                                                <div
                                                    className="mt-0.5 font-mono text-[11px] text-muted-foreground"
                                                    translate="no"
                                                >
                                                    {tool.parameters.join(', ')}
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {tool.summary}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <span
                                                className={cn(
                                                    'rounded px-1.5 py-0.5 text-xs font-medium',
                                                    kindStyle[tool.kind],
                                                )}
                                            >
                                                {tool.kind}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4">
                                            {tool.phase}
                                        </td>
                                        <td className="py-2">
                                            <code
                                                className="font-mono text-xs text-muted-foreground"
                                                translate="no"
                                            >
                                                {`${tool.method} ${tool.path}`}
                                            </code>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5 text-brand" />
                            Datenschutz und Sicherheit
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid list-disc gap-2 pl-5 text-sm">
                            {privacy.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Phasen</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid gap-3 md:grid-cols-2">
                            {phases.map((phase) => (
                                <li
                                    key={phase.phase}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <div className="font-semibold">
                                        {`Phase ${phase.phase}`}
                                        {' · '}
                                        {phase.title}
                                    </div>
                                    <p className="text-muted-foreground">
                                        {phase.text}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Offene Fragen</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid list-disc gap-2 pl-5 text-sm">
                            {questions.map((q) => (
                                <li key={q}>{q}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

McpConcept.layout = {
    breadcrumbs: [{ title: 'MCP-Schnittstelle', href: '/mcp-konzept' }],
};

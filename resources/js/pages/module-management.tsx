import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { CodeBlock } from '@/components/ui/code-block';
import { Switch } from '@/components/ui/switch';

type ModuleInfo = {
    key: string;
    folder: string;
    name: string;
    version: string;
    core: string;
    description: string;
    data_class: string;
    db_schema: string;
    rights: Record<string, { label: string; roles: string[] }>;
    menu_group: string;
    menu: string[];
    collections: string[];
    events: { sendet: string[]; empfaengt: string[] };
    plugins: string[];
    enabled: boolean;
    store: string;
    sql: string;
};

const DATA_CLASSES: Record<string, string> = {
    oeffentlich: 'öffentlich',
    intern: 'intern',
    vertraulich: 'vertraulich',
    besonders: 'besonders schützenswert',
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[10rem_1fr]">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

/** Einstellungen → Module: Fachthemen mit eigener Datenhaltung (docs/KONZEPT-MODULE.md). */
export default function ModuleManagement({
    modules,
    errors,
    violations,
    path,
}: {
    modules: ModuleInfo[];
    errors: string[];
    violations: string[];
    path: string;
}) {
    const toggle = (key: string, enabled: boolean) =>
        router.post(
            `/einstellungen/module/${key}`,
            { enabled },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Module" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Module"
                    description="Fachthemen mit eigenen Rechten, eigenem Menü und eigener Datenhaltung. Ausgeschaltete Module haben weder Menü noch Seiten; ihre Daten bleiben erhalten."
                />

                {errors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Fehlerhafte Module (nicht geladen)</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-5">
                                {errors.map((e) => (
                                    <li key={e} translate="no">
                                        {e}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {violations.length > 0 && (
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>Architekturprüfung: Zugriffe ausserhalb des Moduls</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-5">
                                {violations.map((v) => (
                                    <li key={v} translate="no">
                                        {v}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {modules.length === 0 ? (
                    <EmptyState icon={Boxes} title="Noch keine Module">
                        Module liegen im Ordner {path}/ und werden mit
                        «php artisan intranet:make-module Name --gruppe=…»
                        angelegt.
                    </EmptyState>
                ) : (
                    modules.map((m) => (
                        <Card key={m.key}>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2">
                                    <Boxes className="size-5 text-muted-foreground" />
                                    <span translate="no">{m.name}</span>
                                    <Badge variant="outline">{`v${m.version}`}</Badge>
                                    <Badge
                                        variant={m.enabled ? 'default' : 'secondary'}
                                    >
                                        {m.enabled ? 'eingeschaltet' : 'ausgeschaltet'}
                                    </Badge>
                                    <span className="ml-auto flex items-center gap-2 text-sm font-normal">
                                        <Switch
                                            checked={m.enabled}
                                            onCheckedChange={(v) => toggle(m.key, v)}
                                            aria-label={`${m.name} ein- oder ausschalten`}
                                        />
                                    </span>
                                </CardTitle>
                                {m.description && (
                                    <CardDescription>{m.description}</CardDescription>
                                )}
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <dl className="grid gap-2">
                                    <Row label="Schlüssel / Ordner">
                                        <code translate="no">{`${m.key} · modules/${m.folder}`}</code>
                                    </Row>
                                    <Row label="Benötigt">
                                        <code translate="no">{`Intranet ${m.core}`}</code>
                                    </Row>
                                    <Row label="Datenklasse">
                                        {DATA_CLASSES[m.data_class] ?? m.data_class}
                                    </Row>
                                    <Row label="Speicher">
                                        <code translate="no">{`${m.store} · Schema ${m.db_schema}`}</code>
                                    </Row>
                                    <Row label="Rechte">
                                        <ul className="grid gap-0.5">
                                            {Object.entries(m.rights).map(([ability, r]) => (
                                                <li key={ability}>
                                                    <code translate="no">{ability}</code>
                                                    {` – ${r.label} (${r.roles.join(', ')})`}
                                                </li>
                                            ))}
                                        </ul>
                                    </Row>
                                    <Row label="Menü">
                                        <span translate="no">{`${m.menu_group}: ${m.menu.join(', ') || '–'}`}</span>
                                    </Row>
                                    <Row label="Sammlungen">
                                        <code translate="no">{m.collections.join(', ') || '–'}</code>
                                    </Row>
                                    <Row label="Ereignisse">
                                        <span translate="no">
                                            {`sendet: ${m.events.sendet.join(', ') || '–'} · empfängt: ${m.events.empfaengt.join(', ') || '–'}`}
                                        </span>
                                    </Row>
                                    {m.plugins.length > 0 && (
                                        <Row label="Plugins">
                                            <span translate="no">{m.plugins.join(', ')}</span>
                                        </Row>
                                    )}
                                </dl>
                                <details>
                                    <summary className="cursor-pointer text-muted-foreground">
                                        SQL für eigenes Schema und DB-Benutzer (später PostgreSQL)
                                    </summary>
                                    <CodeBlock code={m.sql} language="sql" className="mt-2" />
                                </details>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}

ModuleManagement.layout = {
    breadcrumbs: [{ title: 'Module', href: '/einstellungen/module' }],
};

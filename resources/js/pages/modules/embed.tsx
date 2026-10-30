import { Head } from '@inertiajs/react';
import {
    ExternalLink,
    GitBranch,
    Info,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { isColorName, palette } from '@/lib/colors';
import { moduleIcon } from '@/lib/module-icons';
import { cn } from '@/lib/utils';

type Embedded = {
    key: string;
    title: string;
    icon: string;
    group: string;
    color: string;
    description: string;
    url: string | null;
    open: 'embed' | 'tab' | 'both';
    repo: string | null;
    stack: string | null;
};

type Frame = { embeddable: boolean | null; reason: string } | null;

/** Schritte, um ein eigenes Projekt (GitHub) als Anwendung einzubinden. */
const steps = [
    'Projekt als eigener Dienst betreiben (Container auf dem Schweizer Server, eigene Subdomain, z. B. snapshot.intranet.sekbuelach.ch).',
    'Anmeldung über das Intranet: Der Reverse-Proxy lässt nur angemeldete Personen durch (Forward-Auth), die Anwendung erhält Name und Rolle.',
    'Einbetten erlauben: Die Anwendung sendet «Content-Security-Policy: frame-ancestors» mit der Intranet-Adresse.',
    'Adresse in der .env eintragen (z. B. INTRANET_APP_WEBSNAPSHOT_URL). Danach erscheint die Anwendung hier im Rahmen.',
];

export default function Embed({
    module,
    frame,
}: {
    module: Embedded;
    frame: Frame;
}) {
    const Icon = moduleIcon(module.icon);
    const color = isColorName(module.color)
        ? palette[module.color]
        : palette.slate;
    const [reloads, setReloads] = useState(0);

    const canEmbed =
        module.url !== null &&
        module.open !== 'tab' &&
        frame?.embeddable !== false;
    const showTab = module.url !== null && module.open !== 'embed';

    return (
        <>
            <Head title={module.title} />
            <div className="flex min-h-0 flex-1 flex-col gap-3 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Icon className={cn('size-6', color.icon)} />
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-semibold">
                            {module.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {module.description}
                        </p>
                    </div>
                    {canEmbed && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setReloads((n) => n + 1)}
                        >
                            <RefreshCw /> Neu laden
                        </Button>
                    )}
                    {showTab && module.url && (
                        <Button asChild size="sm">
                            <a
                                href={module.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink /> In neuem Tab öffnen
                            </a>
                        </Button>
                    )}
                </div>

                {module.url === null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Info className="size-5 text-brand" />
                                Noch nicht verbunden
                            </CardTitle>
                            <CardDescription>
                                Die Anwendung läuft noch nicht als Dienst. So
                                wird sie eingebunden:
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm">
                            {(module.repo || module.stack) && (
                                <div className="flex flex-wrap items-center gap-3">
                                    {module.repo && (
                                        <a
                                            href={`https://github.com/${module.repo}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                                            translate="no"
                                        >
                                            <GitBranch className="size-4" />
                                            {module.repo}
                                        </a>
                                    )}
                                    {module.stack && (
                                        <Badge variant="outline">
                                            <span translate="no">
                                                {module.stack}
                                            </span>
                                        </Badge>
                                    )}
                                </div>
                            )}
                            <ol className="grid list-decimal gap-2 pl-5">
                                {steps.map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                )}

                {module.url !== null && !canEmbed && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldAlert className="size-5 text-brand" />
                                Öffnet in neuem Tab
                            </CardTitle>
                            <CardDescription>
                                {frame?.reason ??
                                    'Diese Seite wird nur in einem neuen Tab geöffnet.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <a
                                    href={module.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink /> In neuem Tab öffnen
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {canEmbed && module.url && (
                    <>
                        {frame?.embeddable === null && (
                            <p className="text-xs text-muted-foreground">
                                <span>{frame.reason}.</span>{' '}
                                <span>
                                    Bleibt der Bereich leer, «In neuem Tab
                                    öffnen» wählen.
                                </span>
                            </p>
                        )}
                        <iframe
                            key={reloads}
                            src={module.url}
                            title={module.title}
                            className="min-h-[calc(100vh-12rem)] w-full flex-1 rounded-lg border bg-background"
                            // Anmeldung und Formulare der eingebundenen Seite zulassen, sonst nichts
                            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"
                            referrerPolicy="strict-origin-when-cross-origin"
                            allow="clipboard-write"
                        />
                    </>
                )}
            </div>
        </>
    );
}

Embed.layout = (props: { module: Embedded }) => ({
    breadcrumbs: [
        { title: props.module.group, href: '#' },
        {
            title: props.module.title,
            href: `/einbindung/${props.module.key}`,
        },
    ],
});

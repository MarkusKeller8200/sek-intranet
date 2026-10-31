import { Head, Link } from '@inertiajs/react';
import {
    ExternalLink,
    GitBranch,
    Info,
    Puzzle,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import FrameDiagnosis from '@/components/frame-diagnosis';
import type { FrameResult } from '@/components/frame-diagnosis';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { isColorName, palette } from '@/lib/colors';
import { connectorVersion, openWithConnector } from '@/lib/connector';
import { moduleIcon } from '@/lib/module-icons';
import { cn } from '@/lib/utils';

type Embedded = {
    key: string;
    title: string;
    icon: string;
    group: string;
    color: string;
    description: string;
    /** Fachanwendung: Seitenmenü beim Öffnen einklappen */
    collapse: boolean;
    url: string | null;
    open: 'embed' | 'tab' | 'both' | 'extension';
    repo: string | null;
    stack: string | null;
};

type Frame = FrameResult | null;

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
    const { setOpen, isMobile } = useSidebar();

    // Fachanwendungen: Seitenmenü einmal je Anwendung einklappen, auch beim direkten Aufruf der Adresse.
    // Nur einmal: setOpen ändert sich mit jedem Öffnen – sonst würde das Menü sofort wieder zugeklappt.
    const collapsedFor = useRef<string | null>(null);
    useEffect(() => {
        if (
            module.collapse &&
            !isMobile &&
            collapsedFor.current !== module.key
        ) {
            collapsedFor.current = module.key;
            setOpen(false);
        }
    }, [module.key, module.collapse, isMobile, setOpen]);

    const viaExtension = module.open === 'extension';
    const canEmbed =
        module.url !== null &&
        module.open !== 'tab' &&
        !viaExtension &&
        frame?.embeddable !== false;
    const showTab = module.url !== null && module.open !== 'embed';

    // «Über die Browser-Erweiterung»: einmal je Aufruf automatisch im eigenen Tab öffnen
    const [extension] = useState(() => connectorVersion());
    const [opened, setOpened] = useState(false);
    const openedFor = useRef<string | null>(null);
    useEffect(() => {
        if (
            viaExtension &&
            extension &&
            module.url &&
            openedFor.current !== module.key
        ) {
            openedFor.current = module.key;
            setOpened(openWithConnector(module.url, module.title));
        }
    }, [viaExtension, extension, module.url, module.key, module.title]);

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

                {module.url !== null && viaExtension && (
                    <Card data-testid="extension-card">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Puzzle className="size-5 text-brand" />
                                {extension
                                    ? 'Über die Browser-Erweiterung geöffnet'
                                    : 'Browser-Erweiterung nicht gefunden'}
                            </CardTitle>
                            <CardDescription>
                                {extension
                                    ? 'Die Anwendung läuft in einem eigenen Tab, gruppiert mit dem Intranet. Aufzeichnen für den API-Recorder: Symbol der Erweiterung im Tab der Anwendung.'
                                    : 'Für diese Anwendung ist «Über die Browser-Erweiterung» eingestellt. Ohne Erweiterung öffnet sie in einem normalen Tab.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {extension ? (
                                <Button
                                    onClick={() =>
                                        setOpened(
                                            openWithConnector(
                                                module.url ?? '',
                                                module.title,
                                            ),
                                        )
                                    }
                                >
                                    <Puzzle />{' '}
                                    {opened ? 'Erneut öffnen' : 'Öffnen'}
                                </Button>
                            ) : (
                                <Button variant="outline" asChild>
                                    <Link href="/einstellungen/browser-erweiterung">
                                        <Puzzle /> Browser-Erweiterung
                                        einrichten
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                {module.url !== null && !canEmbed && !viaExtension && (
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
                        <CardContent className="grid gap-4">
                            {frame?.details && (
                                <FrameDiagnosis result={frame} />
                            )}
                            <Button asChild className="w-fit">
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
                            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"
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

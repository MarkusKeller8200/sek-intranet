import { Head, router } from '@inertiajs/react';
import {
    Bug,
    ExternalLink,
    GitCommitHorizontal,
    Lightbulb,
    Megaphone,
    Tag,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { formatDate, formatDateTime } from '@/lib/format';
import { githubIssueUrl, versionLabel } from '@/lib/github';

type Release = {
    version: string;
    date: string | null;
    sections: { title: string; items: string[] }[];
};

type Info = {
    version: string;
    build: string | null;
    build_date: string | null;
    prototype: boolean;
    dev: { commits: number; base: string; planned: string } | null;
};

/** Markdown-Hervorhebungen im Changelog (**fett**, `code`) einfach darstellen. */
function Inline({ text }: { text: string }) {
    const parts = text.split(/(\*\*[^*]+\*\*|`[^`]+`)/g);

    return (
        <>
            {parts.map((part, i) =>
                part.startsWith('**') ? (
                    <strong key={i}>{part.slice(2, -2)}</strong>
                ) : part.startsWith('`') ? (
                    <code
                        key={i}
                        className="rounded bg-muted px-1 font-mono text-xs"
                    >
                        {part.slice(1, -1)}
                    </code>
                ) : (
                    <span key={i}>{part}</span>
                ),
            )}
        </>
    );
}

export default function Versions({
    info,
    changelog,
    github,
    announcements,
}: {
    info: Info;
    changelog: Release[];
    github: string | null;
    announcements: { enabled: boolean } | null;
}) {
    const version = versionLabel(info);

    return (
        <>
            <Head title="Versionen" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Versionen"
                    description="Was sich im Intranet geändert hat. Versionen nach Semantic Versioning (MAJOR.MINOR.PATCH); eine neue Nummer gibt es nur bei einem freigegebenen Release."
                />

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <span className="flex items-center gap-2 text-lg font-semibold">
                            <Tag className="size-5 text-brand" /> Version{' '}
                            {info.version}
                        </span>
                        {info.prototype && (
                            <Badge variant="outline">Prototyp</Badge>
                        )}
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <GitCommitHorizontal className="size-4" />
                            Build{' '}
                            <code className="font-mono">
                                {info.build ?? 'lokal'}
                            </code>
                            {info.build_date &&
                                ` · ${formatDateTime(info.build_date)}`}
                        </span>
                        {info.dev && (
                            <span className="basis-full text-muted-foreground">
                                <Badge variant="secondary" className="mr-2">
                                    Developer Release
                                </Badge>
                                {`${info.dev.commits} Änderungen seit Version ${info.dev.base}, noch nicht als Version freigegeben. Geplant: ${info.dev.planned}.`}
                            </span>
                        )}
                        {github && (
                            <span className="flex basis-full flex-wrap gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={githubIssueUrl(
                                            github,
                                            'bug_report.yml',
                                            { version },
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Bug /> Fehler melden
                                        <ExternalLink className="opacity-60" />
                                    </a>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={githubIssueUrl(
                                            github,
                                            'feature_request.yml',
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Lightbulb /> Idee vorschlagen
                                        <ExternalLink className="opacity-60" />
                                    </a>
                                </Button>
                            </span>
                        )}
                    </CardContent>
                </Card>

                {announcements && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Megaphone className="size-5 text-brand" />
                                Neuerungen als Mitteilung
                                <span className="ml-auto">
                                    <Switch
                                        checked={announcements.enabled}
                                        onCheckedChange={(enabled) =>
                                            router.post(
                                                '/versionen/neuerungen',
                                                { enabled },
                                                { preserveScroll: true },
                                            )
                                        }
                                        aria-label="Neuerungen als Mitteilung ein- oder ausschalten"
                                    />
                                </span>
                            </CardTitle>
                            <CardDescription>
                                Bei einem freigegebenen Release erscheinen die
                                Punkte «Für Mitarbeitende» einmal als Mitteilung
                                «Neu im Intranet» – Punkte mit Recht nur für
                                Personen mit diesem Recht.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {changelog.map((release) => (
                    <Card key={release.version}>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {release.version === 'Unveröffentlicht'
                                    ? info.dev
                                        ? `In Entwicklung (Ziel ${info.dev.planned})`
                                        : 'Unveröffentlicht'
                                    : `Version ${release.version}`}
                                {release.version === info.version && (
                                    <Badge>aktuell</Badge>
                                )}
                            </CardTitle>
                            {release.date && (
                                <CardDescription>
                                    {formatDate(release.date)}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {release.sections.map((section) => (
                                <div key={section.title} className="grid gap-2">
                                    <h3 className="text-sm font-semibold">
                                        {section.title}
                                    </h3>
                                    <ul
                                        className="grid list-disc gap-1 pl-5 text-sm"
                                        translate="no"
                                    >
                                        {section.items.map((item) => (
                                            <li key={item}>
                                                <Inline text={item} />
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

Versions.layout = {
    breadcrumbs: [{ title: 'Versionen', href: '/versionen' }],
};

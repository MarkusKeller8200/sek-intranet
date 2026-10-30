import { Head } from '@inertiajs/react';
import { GitCommitHorizontal, Tag } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/lib/format';

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
}: {
    info: Info;
    changelog: Release[];
}) {
    return (
        <>
            <Head title="Versionen" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Versionen"
                    description="Was sich im Intranet geändert hat. Versionen nach Semantic Versioning (MAJOR.MINOR.PATCH)."
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
                    </CardContent>
                </Card>

                {changelog.map((release) => (
                    <Card key={release.version}>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {release.version === 'Unveröffentlicht'
                                    ? 'Unveröffentlicht'
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

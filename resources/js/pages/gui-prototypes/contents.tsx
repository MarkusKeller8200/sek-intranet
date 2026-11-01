import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ExternalLink, LayoutList } from 'lucide-react';
import OptionBadge from '@/components/option-badge';
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
import { moduleIcon } from '@/lib/module-icons';

type Entry = {
    key: string;
    title: string;
    icon: string;
    description: string;
    parts: string[];
    released: string | null;
    open: { version: string; status: string } | null;
};

type Category = {
    key: string;
    title: string;
    icon: string;
    description: string;
    items: Entry[];
};

type Topic = { title: string; description: string; href: string | null };

/** Inhaltsverzeichnis der GUI-Bausteine mit Erklärung, Bestandteilen, Status und Link zum Prototyp. */
export default function GuiContents({
    categories,
    crosscutting,
}: {
    categories: Category[];
    crosscutting: Topic[];
}) {
    return (
        <>
            <Head title="GUI-Prototypen" />
            <div className="flex max-w-5xl flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader
                    title="GUI-Prototypen – Inhaltsverzeichnis"
                    description="Alle Bausteine der Oberfläche nach Bereich, mit Erklärung, wofür sie gedacht sind, was sie enthalten und wie weit sie sind. Ein Klick öffnet den Prototyp mit Beispielen."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/gui-prototypen/freigaben">
                                <LayoutList /> Freigaben &amp; Status
                            </Link>
                        </Button>
                    }
                />

                <nav aria-label="Bereiche" className="flex flex-wrap gap-2 text-sm">
                    {categories.map((c) => (
                        <a
                            key={c.key}
                            href={`#${c.key}`}
                            className="rounded-md border px-3 py-1.5 hover:bg-accent"
                        >
                            {c.title}
                        </a>
                    ))}
                    <a
                        href="#querschnitt"
                        className="rounded-md border px-3 py-1.5 hover:bg-accent"
                    >
                        Für alle Bausteine
                    </a>
                </nav>

                {categories.map((category, ci) => {
                    const Icon = moduleIcon(category.icon);

                    return (
                        <section
                            key={category.key}
                            id={category.key}
                            className="grid scroll-mt-20 gap-3"
                        >
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    <Icon className="size-5 text-muted-foreground" />
                                    <span>{`${ci + 1}. ${category.title}`}</span>
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {category.description}
                                </p>
                            </div>
                            <ol className="grid gap-2">
                                {category.items.map((item, ii) => {
                                    const ItemIcon = moduleIcon(item.icon);

                                    return (
                                        <li key={item.key}>
                                            <Card
                                                className="gap-3 py-4"
                                                data-testid={`toc-${item.key}`}
                                            >
                                                <CardHeader className="px-4">
                                                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                                        <span className="text-muted-foreground tabular-nums">
                                                            {`${ci + 1}.${ii + 1}`}
                                                        </span>
                                                        <ItemIcon className="size-4 text-muted-foreground" />
                                                        <Link
                                                            href={`/gui-prototypen/${item.key}`}
                                                            className="hover:underline"
                                                        >
                                                            {item.title}
                                                        </Link>
                                                        {item.released ? (
                                                            <Badge variant="outline">
                                                                {`verbindlich v${item.released}`}
                                                            </Badge>
                                                        ) : null}
                                                        {item.open && (
                                                            <OptionBadge
                                                                list="guiStatuses"
                                                                value={
                                                                    item.open
                                                                        .status
                                                                }
                                                            />
                                                        )}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {item.description}
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="flex flex-wrap items-end justify-between gap-3 px-4">
                                                    <ul className="grid list-disc gap-0.5 pl-5 text-sm text-muted-foreground">
                                                        {item.parts.map((p) => (
                                                            <li key={p}>{p}</li>
                                                        ))}
                                                    </ul>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/gui-prototypen/${item.key}`}
                                                        >
                                                            Zum Prototyp{' '}
                                                            <ArrowRight />
                                                        </Link>
                                                    </Button>
                                                </CardContent>
                                            </Card>
                                        </li>
                                    );
                                })}
                            </ol>
                        </section>
                    );
                })}

                <section id="querschnitt" className="grid scroll-mt-20 gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {`${categories.length + 1}. Für alle Bausteine`}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Regeln, die jeder Baustein erfüllen muss – bei der
                            Prüfung kontrollieren.
                        </p>
                    </div>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {crosscutting.map((topic) => (
                            <li key={topic.title}>
                                <Card className="h-full gap-2 py-4">
                                    <CardHeader className="px-4">
                                        <CardTitle className="text-base">
                                            {topic.href ? (
                                                <Link
                                                    href={topic.href}
                                                    className="inline-flex items-center gap-1.5 hover:underline"
                                                >
                                                    <span>{topic.title}</span>
                                                    <ExternalLink className="size-3.5" />
                                                </Link>
                                            ) : (
                                                topic.title
                                            )}
                                        </CardTitle>
                                        <CardDescription>
                                            {topic.description}
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

GuiContents.layout = {
    breadcrumbs: [{ title: 'GUI-Prototypen', href: '/gui-prototypen' }],
};

import { Head } from '@inertiajs/react';
import { useState } from 'react';
import LanguagePicker, { useLanguage } from '@/components/language-picker';
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
import { CATALOGS, COLUMNS, coverage, LANGUAGES, missing } from '@/lib/i18n';
import type { Language } from '@/lib/i18n';

const codes: {
    language: Language;
    code: string;
    html: string;
    note: string;
}[] = [
    {
        language: 'de-CH',
        code: 'de-CH',
        html: 'de-CH',
        note: 'Standardsprache. Schweizer Rechtschreibung (ss statt ß), Datum TT.MM.JJJJ. Der deutsche Text im Code ist zugleich der Schlüssel.',
    },
    {
        language: 'en',
        code: 'en',
        html: 'en',
        note: 'Britisches Englisch, schulnahe Begriffe (staff, school building, term).',
    },
    {
        language: 'gsw-u-sd-chzh',
        code: 'gsw-u-sd-chzh',
        html: 'gsw-CH',
        note: 'Zürichdeutsch nach Dieth-Schreibung, locker aber verständlich. Fachbegriffe (Login, Export, API) bleiben.',
    },
];

const rules = [
    'Der Code enthält nur deutsche Texte. Übersetzt wird beim Anzeigen: Eine Schicht über der Seite ersetzt Texte und Attribute (title, placeholder, aria-label) anhand der Kataloge.',
    'Kataloge liegen in resources/js/i18n/*.json, je Bereich eine Datei. Ein Eintrag: "Deutsch": ["English", "Züritüütsch"].',
    'Zahlen werden beim Nachschlagen ersetzt: «12 Einträge» und «3 Einträge» teilen sich einen Katalogeintrag.',
    'Daten werden nie übersetzt: Namen, Funktionen, Mitteilungen und Termine stehen in Bereichen mit translate="no". Auswahlwerte (Schulhaus, Rolle) schon.',
    'Neue Texte kommen im selben Commit in alle Kataloge. node tools/i18n-extract.mjs listet fehlende Einträge, ein Test prüft die Kataloge.',
    'Die Sprache gilt je Browser (Einstellungen → Darstellung oder unten in der Seitenleiste). Vor dem ersten Bild wird die Seite kurz verborgen, damit kein Deutsch aufblitzt.',
];

const samples = [
    'Mitteilungen',
    'Personen & Organisation',
    'Speichern',
    'Abbrechen',
    '{n} Einträge',
    'Keine Einträge gefunden.',
];

function lookup(text: string): [string, string] | undefined {
    for (const catalog of Object.values(CATALOGS)) {
        if (catalog[text]) {
            return catalog[text];
        }
    }

    return undefined;
}

export default function LanguageConcept() {
    const language = useLanguage();
    const stats = coverage();
    const [seen, setSeen] = useState<string[]>(() => [...missing].sort());

    return (
        <>
            <Head title="Sprachkonzept" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Sprachkonzept"
                    description="Mehrsprachige Oberfläche nach dem Vorbild TagStudio: Deutsch als Standard, Englisch und Zürichdeutsch als Alternativen."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Sprache wählen</CardTitle>
                        <CardDescription>
                            Wirkt sofort auf die ganze Oberfläche, ohne
                            Neuladen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LanguagePicker />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Grundsätze</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid list-decimal gap-2 pl-5 text-sm">
                            {rules.map((rule) => (
                                <li key={rule}>{rule}</li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sprachen und Codes</CardTitle>
                        <CardDescription>
                            Codes nach BCP 47. Zürichdeutsch: Sprache gsw
                            (Schweizerdeutsch) mit Unterteilung Kanton Zürich.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b">
                                    <th className="py-2 pr-4 font-medium">
                                        Sprache
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Code
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        HTML lang
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Übersetzt
                                    </th>
                                    <th className="py-2 font-medium">
                                        Hinweise
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {codes.map((row) => {
                                    const index = COLUMNS.indexOf(
                                        row.language as (typeof COLUMNS)[number],
                                    );
                                    const done =
                                        index < 0
                                            ? stats.total
                                            : (stats.byLanguage[
                                                  COLUMNS[index]
                                              ] ?? 0);

                                    return (
                                        <tr
                                            key={row.code}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="py-2 pr-4 font-medium">
                                                <span translate="no">
                                                    {
                                                        LANGUAGES[row.language]
                                                            .native
                                                    }
                                                </span>
                                                {row.language === language && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-2"
                                                    >
                                                        aktiv
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <code translate="no">
                                                    {row.code}
                                                </code>
                                            </td>
                                            <td className="py-2 pr-4">
                                                <code translate="no">
                                                    {row.html}
                                                </code>
                                            </td>
                                            <td className="py-2 pr-4 whitespace-nowrap">
                                                {`${done} von ${stats.total}`}
                                            </td>
                                            <td className="py-2 text-muted-foreground">
                                                {row.note}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Beispiele aus den Katalogen</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm" translate="no">
                            <thead className="text-left text-muted-foreground">
                                <tr className="border-b">
                                    <th className="py-2 pr-4 font-medium">
                                        Deutsch
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        English
                                    </th>
                                    <th className="py-2 font-medium">
                                        Züritüütsch
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {samples.map((text) => {
                                    const entry = lookup(text);

                                    return (
                                        <tr
                                            key={text}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                {text}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {entry?.[0] ?? '–'}
                                            </td>
                                            <td className="py-2">
                                                {entry?.[1] ?? '–'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fehlende Texte in diesem Browser</CardTitle>
                        <CardDescription>
                            Texte, die seit dem Laden ohne Übersetzung angezeigt
                            wurden. Nur in Englisch oder Zürichdeutsch erfasst.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSeen([...missing].sort())}
                            >
                                Aktualisieren
                            </Button>
                        </div>
                        {seen.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Keine fehlenden Texte.
                            </p>
                        ) : (
                            <ul
                                className="grid max-h-72 gap-1 overflow-auto rounded-md border p-3 font-mono text-xs"
                                translate="no"
                            >
                                {seen.map((text) => (
                                    <li key={text}>{text}</li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LanguageConcept.layout = {
    breadcrumbs: [{ title: 'Sprachkonzept', href: '/sprachkonzept' }],
};

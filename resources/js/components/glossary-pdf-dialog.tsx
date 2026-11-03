import { FileDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { PdfOptions, PdfScope } from '@/lib/glossary';
import { PDF_DEFAULT } from '@/lib/glossary';

const STORAGE_KEY = 'intranet.glossary.pdf';

const CHOICES: {
    [K in keyof PdfOptions]: { label: string; options: Record<PdfOptions[K], string> };
} = {
    format: { label: 'Format', options: { hoch: 'A4 hoch', quer: 'A4 quer' } },
    umfang: {
        label: 'Umfang',
        options: { kompakt: 'Kompakt', erweitert: 'Erweitert', vollstaendig: 'Vollständig' },
    },
    gruppe: {
        label: 'Gruppiert',
        options: { keine: 'Keine', kategorie: 'Kategorie', herkunft: 'Herkunft', status: 'Status' },
    },
    umbruch: { label: 'Seitenumbruch', options: { keiner: 'Keiner', gruppe: 'Bei Gruppe' } },
};

const HINT: Record<PdfScope, string> = {
    kompakt: 'Eine Zeile je Eintrag, zu lange Texte mit «…».',
    erweitert: 'Offiziell und eigene Bezeichnung, Aliase, Beschreibung – nichts abgeschnitten.',
    vollstaendig: 'Alle Felder inkl. URL, genutzt in, Status, Gültigkeit und Notiz.',
};

function load(): PdfOptions {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') as Partial<PdfOptions>;
        const valid = (Object.keys(CHOICES) as (keyof PdfOptions)[]).every(
            (k) => saved[k] === undefined || saved[k] in CHOICES[k].options,
        );

        return valid ? { ...PDF_DEFAULT, ...saved } : PDF_DEFAULT;
    } catch {
        return PDF_DEFAULT;
    }
}

/** Zwischendialog für den PDF-Export des Glossars (#70); die letzte Wahl bleibt im Browser. */
export default function GlossaryPdfDialog({
    count,
    open,
    onOpenChange,
    onCreate,
}: {
    count: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreate: (options: PdfOptions) => void;
}) {
    const [o, setO] = useState<PdfOptions>(load);
    const set = <K extends keyof PdfOptions>(key: K, value: PdfOptions[K]) =>
        setO((current) => ({ ...current, [key]: value }));

    const create = () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(o));
        } catch {
            // ohne Speicher: Wahl gilt nur jetzt
        }
        onCreate(o);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>PDF erstellen</DialogTitle>
                    <DialogDescription>
                        {`${count} Einträge (aktuelle Filter der Tabelle). Danach im Druckdialog «Als PDF speichern» wählen.`}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    {(Object.keys(CHOICES) as (keyof PdfOptions)[]).map((key) => {
                        const disabled = key === 'umbruch' && o.gruppe === 'keine';

                        return (
                            <div key={key} className="grid gap-1.5 sm:grid-cols-[8rem_1fr] sm:items-center">
                                <span id={`pdf-${key}`} className="text-sm font-medium">
                                    {CHOICES[key].label}
                                </span>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    size="sm"
                                    className="flex-wrap justify-start"
                                    aria-labelledby={`pdf-${key}`}
                                    disabled={disabled}
                                    value={o[key]}
                                    onValueChange={(v: string) => v && set(key, v as PdfOptions[typeof key])}
                                >
                                    {Object.entries(CHOICES[key].options).map(([value, label]) => (
                                        <ToggleGroupItem key={value} value={value} className="px-3">
                                            {label as string}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                                {key === 'umfang' && (
                                    <p className="text-xs text-muted-foreground sm:col-start-2">{HINT[o.umfang]}</p>
                                )}
                            </div>
                        );
                    })}
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Abbrechen
                    </Button>
                    <Button onClick={create} disabled={count === 0}>
                        <FileDown /> Erstellen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

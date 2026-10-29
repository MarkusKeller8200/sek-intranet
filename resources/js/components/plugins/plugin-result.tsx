import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { statusClasses } from '@/lib/colors';
import { cn } from '@/lib/utils';
import type { PluginResult } from '@/types';

const changeColors: Record<string, string> = {
    neu: 'border-success/30 bg-success-soft text-success',
    ändern: 'border-info/30 bg-info-soft text-info',
    gleich: '',
    'fehlt dort': 'border-warning/30 bg-warning-soft text-warning',
};

function cell(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '–';
    }
    if (typeof value === 'boolean') {
        return value ? 'ja' : 'nein';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

/** Ergebnis einer Plugin-Aktion: Meldung, Tabelle, Vorschau mit Auswahl, Protokoll. */
export default function PluginResultView({
    result,
    onApply,
    applying = false,
    canApply = true,
}: {
    result: PluginResult;
    onApply?: (selected: string[]) => void;
    applying?: boolean;
    canApply?: boolean;
}) {
    const rows = result.rows ?? [];
    const selectable = rows.filter(
        (r) => r._selectable !== false && Boolean(r._key),
    );
    const [selected, setSelected] = useState<string[]>(() =>
        selectable.map((r) => String(r._key)),
    );
    const [showLog, setShowLog] = useState(false);
    const preview = result.preview === true && onApply !== undefined;

    const toggle = (key: string, on: boolean) =>
        setSelected((s) =>
            on ? [...new Set([...s, key])] : s.filter((k) => k !== key),
        );

    return (
        <div className="grid gap-3">
            <div
                className={cn(
                    'rounded-md border px-3 py-2 text-sm',
                    statusClasses[
                        result.status === 'error' ? 'error' : result.status
                    ],
                )}
            >
                {result.message}
                {result.ms !== undefined && (
                    <span className="ml-2 opacity-70">({result.ms} ms)</span>
                )}
            </div>

            {rows.length > 0 && result.columns && (
                <div className="max-h-[28rem] overflow-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {preview && (
                                    <TableHead className="w-8">
                                        <Checkbox
                                            aria-label="Alle auswählen"
                                            checked={
                                                selected.length ===
                                                    selectable.length &&
                                                selectable.length > 0
                                            }
                                            onCheckedChange={(
                                                c: boolean | 'indeterminate',
                                            ) =>
                                                setSelected(
                                                    c === true
                                                        ? selectable.map((r) =>
                                                              String(r._key),
                                                          )
                                                        : [],
                                                )
                                            }
                                        />
                                    </TableHead>
                                )}
                                {result.columns.map((c) => (
                                    <TableHead key={c.key}>{c.label}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row, i) => {
                                const key = String(row._key ?? i);
                                const canSelect =
                                    row._selectable !== false &&
                                    Boolean(row._key);

                                return (
                                    <TableRow key={key}>
                                        {preview && (
                                            <TableCell>
                                                {canSelect && (
                                                    <Checkbox
                                                        aria-label="Auswählen"
                                                        checked={selected.includes(
                                                            key,
                                                        )}
                                                        onCheckedChange={(
                                                            c:
                                                                | boolean
                                                                | 'indeterminate',
                                                        ) =>
                                                            toggle(
                                                                key,
                                                                c === true,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        )}
                                        {result.columns?.map((c) => (
                                            <TableCell
                                                key={c.key}
                                                className="max-w-80 truncate whitespace-nowrap"
                                                title={cell(row[c.key])}
                                            >
                                                {c.key === '_change' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            changeColors[
                                                                String(
                                                                    row._change,
                                                                )
                                                            ] ?? ''
                                                        }
                                                    >
                                                        {cell(row._change)}
                                                    </Badge>
                                                ) : (
                                                    cell(row[c.key])
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}

            {preview && (
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        disabled={
                            applying || selected.length === 0 || !canApply
                        }
                        onClick={() => onApply?.(selected)}
                    >
                        {selected.length} übernehmen
                    </Button>
                    {!canApply && (
                        <span className="text-sm text-muted-foreground">
                            Übernehmen ist erst möglich, wenn «Schreibende
                            Aktionen erlauben» eingeschaltet ist.
                        </span>
                    )}
                </div>
            )}

            {result.log && result.log.length > 0 && (
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowLog((v) => !v)}
                    >
                        Protokoll{' '}
                        {showLog
                            ? 'ausblenden'
                            : `anzeigen (${result.log.length})`}
                    </Button>
                    {showLog && (
                        <pre className="mt-1 max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">
                            {result.log.join('\n')}
                        </pre>
                    )}
                </div>
            )}
        </div>
    );
}

import { Braces, CheckCircle2, ListPlus, Plus, TriangleAlert, Wand2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    formatJson,
    insertFields,
    jsonState,
    pretty,
    showValue,
} from '@/lib/api-examples';
import type { ApiField } from '@/lib/api-examples';
import { cn } from '@/lib/utils';

/**
 * Body eines API-Aufrufs bearbeiten (Plugin-Tester und API-Tester): Beispiel einfügen, formatieren, laufende
 * JSON-Prüfung und «Mögliche Werte» mit «Einfügen» je Feld bzw. «Alle Werte einfügen».
 */
export default function ApiBodyEditor({
    id,
    value,
    onChange,
    example,
    fields,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    example?: unknown;
    fields?: ApiField[];
}) {
    const state = jsonState(value);
    const hasExample =
        example !== undefined &&
        example !== null &&
        !(typeof example === 'object' && Object.keys(example).length === 0);
    const sorted = [...(fields ?? [])].sort(
        (a, b) => Number(b.required ?? false) - Number(a.required ?? false),
    );
    const insert = (list: ApiField[]) => {
        const next = insertFields(value, list);
        if (next !== null) {
            onChange(next);
        }
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Body (JSON)</Label>
            <Textarea
                id={id}
                rows={Math.min(16, Math.max(5, value.split('\n').length + 1))}
                className="font-mono text-xs"
                spellCheck={false}
                value={value}
                aria-describedby={`${id}-state`}
                onChange={(e) => onChange(e.target.value)}
            />
            <p
                id={`${id}-state`}
                aria-live="polite"
                className={cn(
                    'flex items-center gap-1.5 text-xs',
                    state.ok ? 'text-muted-foreground' : 'text-warning',
                )}
            >
                {state.ok ? (
                    <CheckCircle2 className="size-3.5" aria-hidden />
                ) : (
                    <TriangleAlert className="size-3.5" aria-hidden />
                )}
                {state.message}
            </p>
            <div className="flex flex-wrap gap-2">
                {hasExample && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onChange(pretty(example))}
                    >
                        <Wand2 /> Beispiel einfügen
                    </Button>
                )}
                {sorted.length > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!state.ok}
                        onClick={() => insert(sorted)}
                    >
                        <ListPlus /> Alle Werte einfügen
                    </Button>
                )}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!state.ok || state.empty}
                    onClick={() => {
                        const next = formatJson(value);
                        if (next !== null) {
                            onChange(next);
                        }
                    }}
                >
                    <Braces /> Formatieren
                </Button>
            </div>

            {sorted.length > 0 && (
                <div className="grid gap-1.5">
                    <p className="text-sm font-medium">Mögliche Werte</p>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Feld</TableHead>
                                <TableHead>Typ</TableHead>
                                <TableHead>Beschreibung</TableHead>
                                <TableHead>Beispiel</TableHead>
                                <TableHead className="w-0">
                                    <span className="sr-only">Aktion</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sorted.map((f) => (
                                <TableRow key={f.name}>
                                    <TableCell
                                        className="font-mono text-xs"
                                        translate="no"
                                    >
                                        {f.name}
                                        {f.required && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                Pflicht
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className="text-xs"
                                        translate="no"
                                    >
                                        {f.type ?? '–'}
                                    </TableCell>
                                    <TableCell className="max-w-72 text-xs whitespace-normal">
                                        {f.help}
                                        {f.choices && f.choices.length > 0 && (
                                            <span
                                                className="block font-mono text-muted-foreground"
                                                translate="no"
                                            >
                                                {f.choices.join(' | ')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className="max-w-56 truncate font-mono text-xs"
                                        translate="no"
                                        title={showValue(f.example)}
                                    >
                                        {showValue(f.example) || '–'}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={!state.ok}
                                            onClick={() => insert([f])}
                                            aria-label={`${f.name} einfügen`}
                                        >
                                            <Plus /> Einfügen
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

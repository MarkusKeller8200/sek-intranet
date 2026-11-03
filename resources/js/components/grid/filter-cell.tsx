import { Filter, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import OptionLabel from './option-label';
import Popover from './popover';
import type { FilterOp, GridColumn, GridFilter } from './types';
import { describe, isActive, opLabel, OPS, VALUELESS } from './utils';

const field =
    'h-8 w-full rounded-md border border-input bg-background px-2 text-sm';

/**
 * Filter einer Spalte in der Filterzeile: Textspalten mit direkter Eingabe («enthält»),
 * alle Spalten mit Bedingungen über das Trichter-Symbol.
 */
export default function FilterCell<T>({
    column,
    filter,
    onChange,
}: {
    column: GridColumn<T>;
    filter: GridFilter | undefined;
    onChange: (filter: GridFilter | undefined) => void;
}) {
    const [open, setOpen] = useState(false);
    const anchor = useRef<HTMLButtonElement>(null);
    const type = column.type ?? 'text';
    const ops = OPS[type];
    const active = isActive(filter);
    const isText = type === 'text' || type === 'email' || type === 'tel';
    const inlineText = isText && (!filter || filter.op === 'contains');

    const draft: GridFilter = filter ?? { op: ops[0] };
    const set = (next: Partial<GridFilter>) => onChange({ ...draft, ...next });

    return (
        <div className="flex items-center gap-0.5">
            {inlineText ? (
                <input
                    className={cn(
                        'h-7 w-full min-w-0 rounded-md border border-input bg-background px-1.5 text-xs font-normal',
                        active && 'border-brand',
                    )}
                    value={filter?.value ?? ''}
                    onChange={(e) =>
                        onChange(
                            e.target.value === ''
                                ? undefined
                                : { op: 'contains', value: e.target.value },
                        )
                    }
                    placeholder="enthält …"
                    aria-label={`Filter ${column.title}`}
                />
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'h-7 min-w-0 flex-1 truncate rounded-md border border-input bg-background px-1.5 text-left text-xs font-normal',
                        active
                            ? 'border-brand text-foreground'
                            : 'text-muted-foreground',
                    )}
                    title={
                        active && filter ? describe(column, filter) : undefined
                    }
                >
                    {active && filter ? describe(column, filter) : 'Alle'}
                </button>
            )}
            <button
                ref={anchor}
                type="button"
                onClick={() => setOpen((v) => !v)}
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-md hover:bg-accent',
                    active ? 'text-brand' : 'text-muted-foreground',
                )}
                aria-label={`Filterbedingung ${column.title}`}
                title="Filterbedingung"
            >
                <Filter className="size-3.5" />
            </button>

            <Popover
                anchor={anchor}
                open={open}
                onClose={() => setOpen(false)}
                resizable
                height={
                    draft.op === 'in' || draft.op === 'notIn'
                        ? Math.min(420, 170 + (Object.keys(column.options ?? {}).length + 1) * 30)
                        : undefined
                }
            >
                <div className="flex min-h-0 flex-1 flex-col gap-2">
                    <div className="font-medium">Filter «{column.title}»</div>
                    <select
                        className={field}
                        value={draft.op}
                        onChange={(e) =>
                            set({ op: e.target.value as FilterOp })
                        }
                        aria-label="Bedingung"
                    >
                        {ops.map((op) => (
                            <option key={op} value={op}>
                                {opLabel(column, op)}
                            </option>
                        ))}
                    </select>

                    {(draft.op === 'in' || draft.op === 'notIn') && (
                        <div className="grid min-h-16 flex-1 content-start gap-1 overflow-y-auto rounded-md border p-2">
                            {[
                                ...Object.entries(column.options ?? {}),
                                ['', '(leer)'] as const,
                            ].map(([key, label]) => {
                                const checked = (draft.values ?? []).includes(
                                    key,
                                );

                                return (
                                    <label
                                        key={key}
                                        className="flex cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() =>
                                                set({
                                                    values: checked
                                                        ? (
                                                              draft.values ?? []
                                                          ).filter(
                                                              (v) => v !== key,
                                                          )
                                                        : [
                                                              ...(draft.values ??
                                                                  []),
                                                              key,
                                                          ],
                                                })
                                            }
                                        />
                                        <OptionLabel
                                            label={label}
                                            color={column.colors?.[key]}
                                        />
                                    </label>
                                );
                            })}
                        </div>
                    )}

                    {!VALUELESS.includes(draft.op) &&
                        draft.op !== 'in' &&
                        draft.op !== 'notIn' && (
                            <input
                                className={field}
                                type={type === 'date' ? 'date' : 'text'}
                                value={draft.value ?? ''}
                                onChange={(e) => set({ value: e.target.value })}
                                aria-label="Wert"
                                autoFocus
                            />
                        )}
                    {draft.op === 'between' && (
                        <input
                            className={field}
                            type="date"
                            value={draft.value2 ?? ''}
                            onChange={(e) => set({ value2: e.target.value })}
                            aria-label="bis"
                        />
                    )}

                    <div className="flex justify-between gap-2 pt-1">
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                onChange(undefined);
                                setOpen(false);
                            }}
                        >
                            <X /> Entfernen
                        </Button>
                        <Button size="sm" onClick={() => setOpen(false)}>
                            Fertig
                        </Button>
                    </div>
                </div>
            </Popover>
        </div>
    );
}

import {
    Bookmark,
    Check,
    ChevronDown,
    Save,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { postJson } from '@/lib/http';
import type { GridViewState, SavedView } from './types';

type SaveResponse = { view?: SavedView; message?: string; status?: string };

async function deleteView(id: string): Promise<boolean> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );
    const response = await fetch(`/ansichten/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
    });

    return response.ok;
}

/**
 * Gespeicherte Ansichten: Filter, Sortierung, Spalten, Gruppierung unter einem Namen.
 * Persönlich oder (Administration) für alle.
 */
export default function ViewsMenu({
    grid,
    views,
    onViewsChange,
    current,
    canShare,
    onApply,
    onReset,
}: {
    grid: string;
    views: SavedView[];
    onViewsChange: (views: SavedView[]) => void;
    current: GridViewState;
    canShare: boolean;
    onApply: (view: SavedView) => void;
    onReset: () => void;
}) {
    const [dialog, setDialog] = useState(false);
    const [name, setName] = useState('');
    const [shared, setShared] = useState(false);
    const active = views.find((v) => v.id === current.viewId) ?? null;
    const canUpdate =
        active !== null && (active.mine || (active.shared && canShare));

    const save = async (
        id: string | null,
        viewName: string,
        isShared: boolean,
    ) => {
        const state: GridViewState = { ...current, viewId: undefined };
        const res = await postJson<SaveResponse>('/ansichten', {
            grid,
            id,
            name: viewName,
            shared: isShared,
            state,
        });
        if (!res.view) {
            toast.error(res.message ?? 'Ansicht nicht gespeichert.');

            return;
        }
        const saved = res.view;
        onViewsChange(
            [...views.filter((v) => v.id !== saved.id), saved].sort((a, b) =>
                a.name.localeCompare(b.name, 'de-CH'),
            ),
        );
        onApply(saved);
        toast.success(`Ansicht «${saved.name}» gespeichert.`);
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                        <Bookmark /> {active ? active.name : 'Ansicht'}
                        <ChevronDown className="opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-64">
                    <DropdownMenuItem onSelect={onReset}>
                        {current.viewId ? (
                            <span className="size-4" />
                        ) : (
                            <Check />
                        )}{' '}
                        Standard
                    </DropdownMenuItem>
                    {views.length > 0 && <DropdownMenuSeparator />}
                    {views.map((view) => (
                        <DropdownMenuItem
                            key={view.id}
                            onSelect={() => onApply(view)}
                        >
                            {view.id === current.viewId ? (
                                <Check />
                            ) : (
                                <span className="size-4" />
                            )}
                            <span className="flex-1 truncate">{view.name}</span>
                            {view.shared && (
                                <Users
                                    className="text-muted-foreground"
                                    aria-label="für alle"
                                />
                            )}
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                        Aktuelle Einstellungen
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        onSelect={() => {
                            setName('');
                            setShared(false);
                            setDialog(true);
                        }}
                    >
                        <Save /> Als neue Ansicht speichern …
                    </DropdownMenuItem>
                    {active && canUpdate && (
                        <DropdownMenuItem
                            onSelect={() =>
                                void save(active.id, active.name, active.shared)
                            }
                        >
                            <Save /> «{active.name}» aktualisieren
                        </DropdownMenuItem>
                    )}
                    {active && canUpdate && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() =>
                                void deleteView(active.id).then((ok) => {
                                    if (ok) {
                                        onViewsChange(
                                            views.filter(
                                                (v) => v.id !== active.id,
                                            ),
                                        );
                                        onReset();
                                        toast.success('Ansicht gelöscht.');
                                    } else {
                                        toast.error('Löschen nicht möglich.');
                                    }
                                })
                            }
                        >
                            <Trash2 /> «{active.name}» löschen
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={dialog} onOpenChange={setDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ansicht speichern</DialogTitle>
                        <DialogDescription>
                            Speichert Filter, Sortierung, Gruppierung, Suche und
                            Spalten (Reihenfolge, Breite, Sichtbarkeit,
                            Fixierung).
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <Input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="z. B. Lehrpersonen Hinterbirch"
                            maxLength={60}
                            aria-label="Name der Ansicht"
                            autoFocus
                        />
                        {canShare && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={shared}
                                    onChange={(e) =>
                                        setShared(e.target.checked)
                                    }
                                />
                                Für alle sichtbar
                            </label>
                        )}
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Abbrechen</Button>
                        </DialogClose>
                        <Button
                            disabled={name.trim() === ''}
                            onClick={() => {
                                setDialog(false);
                                void save(null, name.trim(), shared);
                            }}
                        >
                            Speichern
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

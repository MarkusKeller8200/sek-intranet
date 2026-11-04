import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Term } from '@/components/ui/term';
import { setTermHighlight, useTermHighlight } from '@/lib/glossary-terms';

/** Einstellung «Begriffe hervorheben» (#60): Glossar-Begriffe gepunktet unterstreichen, gilt für diesen Browser. */
export default function TermHighlightSetting() {
    const on = useTermHighlight();

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-3">
                <Switch
                    id="term-highlight"
                    checked={on}
                    onCheckedChange={setTermHighlight}
                    aria-describedby="term-highlight-hint"
                />
                <Label htmlFor="term-highlight">Begriffe hervorheben</Label>
            </div>
            <p
                id="term-highlight-hint"
                className="text-sm text-muted-foreground"
            >
                Zum Ausprobieren: mit der Maus darüberfahren oder antippen.
            </p>
            <p className="flex gap-4 text-sm" translate="no">
                <Term match="VSA" />
                <Term match="LP" />
            </p>
        </div>
    );
}

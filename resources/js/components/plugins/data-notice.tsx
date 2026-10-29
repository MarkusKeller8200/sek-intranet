import { ShieldAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** Warnung, solange der Server nicht in der Schweiz steht (IDA ADR-005). */
export default function DataNotice({ notice }: { notice?: string | null }) {
    return (
        <Alert className="border-warning/30 bg-warning-soft text-warning">
            <ShieldAlert />
            <AlertTitle>Nur mit Testdaten verwenden</AlertTitle>
            <AlertDescription className="text-warning">
                Dieser Prototyp läuft nicht auf einem Server in der Schweiz.
                Echte Zugangsdaten und Personen- oder Gerätedaten erst auf der
                Schweizer Umgebung (Infomaniak, IDA ADR-005) verwenden.
                {notice ? ` ${notice}` : ''}
            </AlertDescription>
        </Alert>
    );
}

import { CheckCircle2, ShieldAlert, ShieldQuestion } from 'lucide-react';

export type FrameResult = {
    embeddable: boolean | null;
    reason: string;
    details?: {
        x_frame_options: string | null;
        frame_ancestors: string | null;
        server: string | null;
        cloudflare: boolean;
        status: number | null;
    };
};

/** Ergebnis der Einbettungsprüfung mit den Kopfzeilen der Seite (wer verhindert das Einbetten?). */
export default function FrameDiagnosis({ result }: { result: FrameResult }) {
    const d = result.details;
    const Icon =
        result.embeddable === true ? CheckCircle2 : result.embeddable === false ? ShieldAlert : ShieldQuestion;

    return (
        <div className="grid gap-2 rounded-md border bg-muted/30 p-3 text-xs" data-testid="frame-diagnosis">
            <p className="flex items-start gap-2 text-sm">
                <Icon
                    className={
                        result.embeddable === true
                            ? 'mt-0.5 size-4 shrink-0 text-success'
                            : 'mt-0.5 size-4 shrink-0 text-warning'
                    }
                />
                <span>{result.reason}</span>
            </p>
            {d && (
                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    <dt className="text-muted-foreground">X-Frame-Options</dt>
                    <dd className="font-mono break-all" translate="no">
                        {d.x_frame_options ?? '–'}
                    </dd>
                    <dt className="text-muted-foreground">frame-ancestors</dt>
                    <dd className="font-mono break-all" translate="no">
                        {d.frame_ancestors ?? '–'}
                    </dd>
                    <dt className="text-muted-foreground">Server</dt>
                    <dd className="font-mono" translate="no">
                        {d.server ?? '–'}
                    </dd>
                    <dt className="text-muted-foreground">Cloudflare</dt>
                    <dd>{d.cloudflare ? 'ja' : 'nein'}</dd>
                </dl>
            )}
            <p className="text-muted-foreground">
                Die Regel setzt die eingebettete Website selbst. Ändern kann sie nur ihr Betreiber, z. B. mit
                «frame-ancestors 'self' https://intranet.sekbuelach.ch».
            </p>
        </div>
    );
}

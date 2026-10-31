import { Head } from '@inertiajs/react';
import { CheckCircle2, Download, Puzzle, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { connectorVersion } from '@/lib/connector';

/** Einstellungen → Browser-Erweiterung «SBI-Connector» (Prototyp). */
export default function BrowserExtension({ version, origin }: { version: string; origin: string }) {
    const [installed] = useState(() => connectorVersion());

    return (
        <>
            <Head title="Browser-Erweiterung" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Browser-Erweiterung «SBI-Connector»"
                    description="Prototyp für Edge und Chrome: öffnet Fachanwendungen im eigenen Tab neben dem Intranet und zeichnet für den API-Recorder auf – auch auf Websites, die Lesezeichen blockieren."
                    actions={
                        <Button asChild>
                            <a href="/einstellungen/browser-erweiterung/download" data-testid="extension-download">
                                <Download /> Herunterladen (ZIP)
                            </a>
                        </Button>
                    }
                />

                {installed ? (
                    <Alert>
                        <CheckCircle2 />
                        <AlertTitle>Installiert</AlertTitle>
                        <AlertDescription>
                            <span>In diesem Browser ist der SBI-Connector aktiv. Version:</span>{' '}
                            <span translate="no">{installed}</span>
                            {installed !== version && (
                                <>
                                    {' '}
                                    <span>Neuere Version zum Herunterladen:</span>{' '}
                                    <span translate="no">{version}</span>
                                </>
                            )}
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <Puzzle />
                        <AlertTitle>Nicht installiert</AlertTitle>
                        <AlertDescription>
                            In diesem Browser ist der SBI-Connector noch nicht aktiv.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Was die Erweiterung kann</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid list-disc gap-1.5 pl-5 text-sm">
                            <li>
                                Fachanwendungen mit der Einstellung «Über die Browser-Erweiterung» öffnen in einem eigenen
                                Tab, gruppiert mit dem Intranet («Intranet»).
                            </li>
                            <li>
                                API-Recorder: Im Tab einer Website auf das Symbol der Erweiterung → «Aufnahme starten». Die
                                Anfragen erscheinen im offenen API-Recorder – ohne Lesezeichen und ohne Konsole.
                            </li>
                            <li>Die Aufnahme läuft nach dem Neuladen der Seite weiter, bis sie beendet wird.</li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Installation (Prototyp)</CardTitle>
                        <CardDescription>
                            Später über Intune an alle Schulgeräte verteilt; bis dahin von Hand.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid list-decimal gap-1.5 pl-5 text-sm">
                            <li>ZIP herunterladen und entpacken.</li>
                            <li>
                                <span>Erweiterungsseite öffnen:</span>{' '}
                                <span className="font-mono" translate="no">
                                    edge://extensions · chrome://extensions
                                </span>
                            </li>
                            <li>«Entwicklermodus» einschalten.</li>
                            <li>«Entpackte Erweiterung laden» → Ordner «sbi-connector» wählen.</li>
                            <li>Diese Seite neu laden – oben erscheint «Installiert».</li>
                        </ol>
                        <p className="mt-3 text-xs text-muted-foreground">
                            <span>Die Erweiterung ist auf dieses Intranet eingestellt:</span>{' '}
                            <span translate="no">{origin}</span>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5 text-success" /> Datenschutz und Sicherheit
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid list-disc gap-1.5 pl-5 text-sm">
                            <li>Zugriff nur auf das Intranet; andere Websites erst nach Freigabe je Website im Browser.</li>
                            <li>
                                Keine Passwörter, Tokens oder Cookies werden gespeichert; die Anmeldung bei Fachanwendungen
                                bleibt im eigenen Browser.
                            </li>
                            <li>
                                Aufgezeichnete Anfragen gehen nur an den offenen API-Recorder in diesem Browser; ins
                                Intranet gelangt nur, was dort gespeichert wird (maskiert).
                            </li>
                            <li>Nur Websites aufzeichnen, die man nutzen darf; Nutzungsbedingungen beachten.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BrowserExtension.layout = {
    breadcrumbs: [
        { title: 'Einstellungen', href: '/einstellungen/fachanwendungen' },
        { title: 'Browser-Erweiterung', href: '/einstellungen/browser-erweiterung' },
    ],
};

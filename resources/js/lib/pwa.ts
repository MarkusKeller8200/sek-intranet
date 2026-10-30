/**
 * PWA: Service Worker registrieren und Installation anbieten (docs/KONZEPT-PWA.md).
 * Chrome/Edge/Android liefern «beforeinstallprompt»; iOS/iPadOS installiert über
 * Teilen → «Zum Home-Bildschirm», dafür zeigen wir eine Anleitung.
 */

type InstallPrompt = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type InstallState = 'installed' | 'available' | 'ios' | 'unsupported';

let deferred: InstallPrompt | null = null;
let installed = false;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((l) => l());

export function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: window-controls-overlay)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

export function isIos(): boolean {
    const ua = navigator.userAgent;

    // iPadOS meldet sich als Mac mit Touch
    return /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
}

export function installState(): InstallState {
    if (typeof window === 'undefined') {
        return 'unsupported';
    }
    if (installed || isStandalone()) {
        return 'installed';
    }
    if (deferred) {
        return 'available';
    }

    return isIos() ? 'ios' : 'unsupported';
}

export function subscribeInstall(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Installationsdialog des Browsers öffnen; true, wenn angenommen. */
export async function promptInstall(): Promise<boolean> {
    if (!deferred) {
        return false;
    }
    const prompt = deferred;
    deferred = null;
    await prompt.prompt();
    const choice = await prompt.userChoice;
    notify();

    return choice.outcome === 'accepted';
}

/** Beim Start aufrufen: Ereignisse abonnieren und Service Worker registrieren. */
export function initializePwa(build: string | null) {
    if (typeof window === 'undefined') {
        return;
    }
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault(); // eigener Knopf statt Leiste des Browsers
        deferred = event as InstallPrompt;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        installed = true;
        deferred = null;
        notify();
    });
    window.matchMedia('(display-mode: standalone)').addEventListener('change', notify);

    if ('serviceWorker' in navigator && window.isSecureContext) {
        const register = () =>
            navigator.serviceWorker
                .register(`/sw.js?v=${encodeURIComponent(build ?? 'dev')}`, { scope: '/' })
                .catch(() => {
                    // ohne Service Worker läuft das Intranet normal weiter
                });
        if (document.readyState === 'complete') {
            void register();
        } else {
            window.addEventListener('load', () => void register(), { once: true });
        }
    }
}

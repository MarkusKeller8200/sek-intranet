import { useSyncExternalStore } from 'react';
import { getLanguage, LANGUAGES, setLanguage, subscribe } from '@/lib/i18n';
import type { Language } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Flaggen als SVG (Emoji-Flaggen fehlen unter Windows). */
function Flag({ language, className }: { language: Language; className?: string }) {
    const cls = cn('h-3.5 w-5 shrink-0 rounded-[2px] ring-1 ring-black/10', className);

    if (language === 'en') {
        return (
            <svg viewBox="0 0 60 36" className={cls} aria-hidden>
                <rect width="60" height="36" fill="#012169" />
                <path d="M0 0l60 36M60 0L0 36" stroke="#fff" strokeWidth="7" />
                <path d="M0 0l60 36M60 0L0 36" stroke="#C8102E" strokeWidth="3" />
                <path d="M30 0v36M0 18h60" stroke="#fff" strokeWidth="11" />
                <path d="M30 0v36M0 18h60" stroke="#C8102E" strokeWidth="6" />
            </svg>
        );
    }
    if (language === 'gsw-u-sd-chzh') {
        // Kanton Zürich: schräg geteilt Weiss und Blau
        return (
            <svg viewBox="0 0 30 20" className={cls} aria-hidden>
                <rect width="30" height="20" fill="#fff" />
                <path d="M30 0v20H0z" fill="#0F05A0" />
            </svg>
        );
    }

    // Deutsch: Schweiz
    return (
        <svg viewBox="0 0 30 20" className={cls} aria-hidden>
            <rect width="30" height="20" fill="#DA291C" />
            <path d="M13 4h4v4.5h4.5v3H17V16h-4v-4.5H8.5v-3H13z" fill="#fff" />
        </svg>
    );
}

export function useLanguage(): Language {
    return useSyncExternalStore(subscribe, getLanguage, (): Language => 'de-CH');
}

/** Sprachwahl mit Flaggen (Einstellungen → Darstellung). Namen in der jeweiligen Sprache. */
export default function LanguagePicker() {
    const language = useLanguage();

    return (
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Sprache" translate="no">
            {(Object.keys(LANGUAGES) as Language[]).map((key) => (
                <button
                    key={key}
                    type="button"
                    role="radio"
                    aria-checked={language === key}
                    onClick={() => setLanguage(key)}
                    className={cn(
                        'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-accent',
                        language === key && 'border-primary ring-1 ring-primary',
                    )}
                >
                    <Flag language={key} />
                    <span className="font-medium">{LANGUAGES[key].native}</span>
                    {key === 'de-CH' && (
                        <span className="text-xs text-muted-foreground">Standard</span>
                    )}
                </button>
            ))}
        </div>
    );
}

/** Kompakte Sprachwahl für die Seitenleiste (eingeklappt nur die Flagge). */
export function LanguageSelect() {
    const language = useLanguage();

    return (
        <label
            className="flex items-center gap-2 rounded-md px-2 py-1 text-xs text-sidebar-foreground/70 hover:bg-sidebar-accent"
            translate="no"
            title="Sprache / Language / Sprooch"
        >
            <Flag language={language} />
            <select
                value={language}
                onChange={(e) => setLanguage(e.target.value as Language)}
                className="min-w-0 flex-1 cursor-pointer bg-transparent outline-none group-data-[collapsible=icon]:hidden"
                aria-label="Sprache"
            >
                {(Object.keys(LANGUAGES) as Language[]).map((key) => (
                    <option key={key} value={key}>
                        {LANGUAGES[key].native}
                    </option>
                ))}
            </select>
        </label>
    );
}

import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Step = { title: string; description?: string };

/** Schrittanzeige für Abläufe in Schritten. GUI-Prototyp «Schrittanzeige». */
function Stepper({ steps, current, className }: { steps: Step[]; current: number; className?: string }) {
    return (
        <ol className={cn('grid gap-3 sm:flex sm:items-start sm:gap-0', className)}>
            {steps.map((step, i) => {
                const state = i < current ? 'done' : i === current ? 'current' : 'upcoming';

                return (
                    <li
                        key={step.title}
                        aria-current={state === 'current' ? 'step' : undefined}
                        className="flex flex-1 items-start gap-2 sm:flex-col sm:items-center sm:text-center"
                    >
                        <div className="flex w-full items-center sm:justify-center">
                            <span
                                className={cn(
                                    'grid size-8 shrink-0 place-items-center rounded-full border-2 text-sm font-semibold tabular-nums',
                                    state === 'done' && 'border-primary bg-primary text-primary-foreground',
                                    state === 'current' && 'border-primary text-primary',
                                    state === 'upcoming' && 'border-muted-foreground/30 text-muted-foreground',
                                )}
                            >
                                {state === 'done' ? <Check className="size-4" aria-hidden /> : i + 1}
                            </span>
                        </div>
                        <div className="grid gap-0.5">
                            <span className={cn('text-sm font-medium', state === 'upcoming' && 'text-muted-foreground')}>
                                {step.title}
                            </span>
                            {step.description && <span className="text-xs text-muted-foreground">{step.description}</span>}
                            <span className="sr-only">
                                {state === 'done' ? '(erledigt)' : state === 'current' ? '(aktueller Schritt)' : '(offen)'}
                            </span>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export { Stepper };

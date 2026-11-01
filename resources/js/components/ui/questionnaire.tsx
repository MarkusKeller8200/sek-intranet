import { ArrowLeft, ArrowRight, Check, SkipForward } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type Question = {
    id: string;
    type: 'single' | 'multiple' | 'text';
    title: string;
    hint?: string;
    options?: string[];
    required?: boolean;
};

export type Answers = Record<string, string | string[] | null>;

/**
 * Fragebogen in Schritten: eine Frage pro Schritt, Einfach-/Mehrfachauswahl oder Freitext, überspringbar,
 * am Schluss Übersicht. GUI-Prototyp «Fragebogen» (Modul «Umfragen»).
 */
function Questionnaire({ questions, onSubmit }: { questions: Question[]; onSubmit: (answers: Answers) => void }) {
    const [step, setStep] = useState(0);
    const [answers, setAnswers] = useState<Answers>({});
    const base = useId();
    const done = step >= questions.length;
    const q = questions[step];
    const value = q ? answers[q.id] : null;
    const answered = q ? (Array.isArray(value) ? value.length > 0 : typeof value === 'string' && value.trim() !== '') : true;
    const set = (v: string | string[] | null) => q && setAnswers((a) => ({ ...a, [q.id]: v }));

    return (
        <div className="grid gap-4">
            <Progress label={done ? 'Fertig' : `Frage ${step + 1} von ${questions.length}`} value={Math.round((Math.min(step, questions.length) / questions.length) * 100)} />
            {done ? (
                <div className="grid gap-3">
                    <h3 className="font-semibold">Übersicht</h3>
                    <dl className="grid gap-2 text-sm">
                        {questions.map((question, i) => {
                            const a = answers[question.id];

                            return (
                                <div key={question.id} className="grid gap-0.5 rounded-md border p-2">
                                    <dt className="text-muted-foreground">{question.title}</dt>
                                    <dd className="flex items-center justify-between gap-2">
                                        <span>{a == null || (Array.isArray(a) && a.length === 0) || a === '' ? '– (übersprungen)' : Array.isArray(a) ? a.join(', ') : a}</span>
                                        <Button type="button" variant="link" size="sm" onClick={() => setStep(i)}>
                                            Ändern
                                        </Button>
                                    </dd>
                                </div>
                            );
                        })}
                    </dl>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => setStep(questions.length - 1)}>
                            <ArrowLeft /> Zurück
                        </Button>
                        <Button type="button" onClick={() => onSubmit(answers)}>
                            <Check /> Absenden
                        </Button>
                    </div>
                </div>
            ) : (
                <fieldset className="grid gap-3">
                    <legend className="mb-1 font-semibold">
                        {q.title}
                        {q.required && <span className="text-destructive-foreground"> *</span>}
                    </legend>
                    {q.hint && <p className="text-sm text-muted-foreground">{q.hint}</p>}
                    {q.type === 'single' &&
                        q.options?.map((o) => (
                            <label key={o} className={cn('flex cursor-pointer items-center gap-2 rounded-md border p-2 text-sm hover:bg-accent/50', value === o && 'border-primary bg-accent/40')}>
                                <input type="radio" name={`${base}-${q.id}`} checked={value === o} onChange={() => set(o)} className="size-4 accent-primary" />
                                <span>{o}</span>
                            </label>
                        ))}
                    {q.type === 'multiple' &&
                        q.options?.map((o) => {
                            const list = Array.isArray(value) ? value : [];

                            return (
                                <label key={o} className={cn('flex cursor-pointer items-center gap-2 rounded-md border p-2 text-sm hover:bg-accent/50', list.includes(o) && 'border-primary bg-accent/40')}>
                                    <Checkbox checked={list.includes(o)} onCheckedChange={(c: boolean | 'indeterminate') => set(c === true ? [...list, o] : list.filter((x) => x !== o))} />
                                    <span>{o}</span>
                                </label>
                            );
                        })}
                    {q.type === 'text' && <Textarea rows={3} value={typeof value === 'string' ? value : ''} onChange={(e) => set(e.target.value)} aria-label={q.title} />}
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" disabled={step === 0} onClick={() => setStep((s) => s - 1)}>
                            <ArrowLeft /> Zurück
                        </Button>
                        {!q.required && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    set(null);
                                    setStep((s) => s + 1);
                                }}
                            >
                                <SkipForward /> Überspringen
                            </Button>
                        )}
                        <Button type="button" disabled={q.required && !answered} onClick={() => setStep((s) => s + 1)}>
                            Weiter <ArrowRight />
                        </Button>
                    </div>
                </fieldset>
            )}
        </div>
    );
}

export { Questionnaire };

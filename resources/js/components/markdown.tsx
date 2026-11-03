import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Markdown sicher anzeigen (ohne HTML): Überschriften (#, ##, ###), Absätze, Listen (-, *, 1.), **fett**, *kursiv*,
 * `Code`, Links [Text](https://…) und reine https-Links. Alles andere bleibt Text.
 */
function inline(text: string, keyBase: string): ReactNode[] {
    const out: ReactNode[] = [];
    const re =
        /(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|`[^`]+`|\[[^\]]+\]\((https?:\/\/[^)\s]+)\)|https?:\/\/[^\s)]+)/g;
    let last = 0;
    let m: RegExpExecArray | null;
    let i = 0;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) {
            out.push(text.slice(last, m.index));
        }
        const t = m[0];
        const key = `${keyBase}-${i++}`;
        if (t.startsWith('**')) {
            out.push(<strong key={key}>{t.slice(2, -2)}</strong>);
        } else if (t.startsWith('`')) {
            out.push(
                <code key={key} className="rounded bg-muted px-1 font-mono text-xs">
                    {t.slice(1, -1)}
                </code>,
            );
        } else if (t.startsWith('[')) {
            const label = t.slice(1, t.indexOf(']'));
            out.push(
                <a key={key} href={m[2]} target="_blank" rel="noopener noreferrer" className="text-primary underline underline-offset-2">
                    {label}
                </a>,
            );
        } else if (t.startsWith('http')) {
            out.push(
                <a key={key} href={t} target="_blank" rel="noopener noreferrer" className="text-primary underline underline-offset-2">
                    {t}
                </a>,
            );
        } else {
            out.push(<em key={key}>{t.slice(1, -1)}</em>);
        }
        last = m.index + t.length;
    }
    if (last < text.length) {
        out.push(text.slice(last));
    }

    return out;
}

type Liste = { ordered: boolean; items: string[] };

export default function Markdown({ text, className }: { text: string; className?: string }) {
    const blocks: ReactNode[] = [];
    const lines = text.replace(/\r\n?/g, '\n').split('\n');
    let para: string[] = [];
    let list: Liste | null = null;
    const flush = () => {
        if (para.length > 0) {
            const k = `p${blocks.length}`;
            blocks.push(<p key={k}>{inline(para.join(' '), k)}</p>);
            para = [];
        }
        if (list) {
            const k = `l${blocks.length}`;
            const items = list.items.map((it, i) => <li key={i}>{inline(it, `${k}-${i}`)}</li>);
            blocks.push(
                list.ordered ? (
                    <ol key={k} className="list-decimal pl-5">{items}</ol>
                ) : (
                    <ul key={k} className="list-disc pl-5">{items}</ul>
                ),
            );
            list = null;
        }
    };
    for (const line of lines) {
        const h = /^(#{1,3})\s+(.*)$/.exec(line);
        const li = /^\s*([-*]|\d+\.)\s+(.*)$/.exec(line);
        if (line.trim() === '') {
            flush();
        } else if (h) {
            flush();
            const k = `h${blocks.length}`;
            const cls = h[1].length === 1 ? 'text-lg font-semibold' : 'font-semibold';
            blocks.push(
                <p key={k} className={cls}>
                    {inline(h[2], k)}
                </p>,
            );
        } else if (li) {
            if (para.length > 0) {
                const keep = list as Liste | null;
                list = null;
                flush();
                list = keep;
            }
            const ordered = /\d+\./.test(li[1]);
            if (list && list.ordered !== ordered) {
                flush();
            }
            list ??= { ordered, items: [] };
            list.items.push(li[2]);
        } else {
            if (list) {
                flush();
            }
            para.push(line.trim());
        }
    }
    flush();

    return <div className={cn('grid gap-2 text-sm leading-relaxed', className)}>{blocks}</div>;
}

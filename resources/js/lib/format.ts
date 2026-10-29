const locale = 'de-CH';
const timeZone = 'Europe/Zurich';

const dateFormat = new Intl.DateTimeFormat(locale, {
    timeZone,
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});
const shortDateFormat = new Intl.DateTimeFormat(locale, {
    timeZone,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});
const timeFormat = new Intl.DateTimeFormat(locale, {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
});
const monthFormat = new Intl.DateTimeFormat(locale, {
    timeZone,
    month: 'long',
    year: 'numeric',
});

export function formatDate(value: string | null | undefined): string {
    return value ? dateFormat.format(new Date(value)) : '';
}

export function formatShortDate(value: string | null | undefined): string {
    return value ? shortDateFormat.format(new Date(value)) : '';
}

export function formatDateTime(value: string | null | undefined): string {
    return value
        ? `${shortDateFormat.format(new Date(value))}, ${timeFormat.format(new Date(value))}`
        : '';
}

export function formatMonth(value: string): string {
    return monthFormat.format(new Date(value));
}

/** Zeitraum eines Termins, z. B. «Do., 5. November 2026, 16:45–18:30». */
export function formatEventRange(
    startsAt: string,
    endsAt: string | null,
    allDay: boolean,
): string {
    const start = new Date(startsAt);
    const end = endsAt ? new Date(endsAt) : null;

    if (allDay) {
        if (
            !end ||
            shortDateFormat.format(start) === shortDateFormat.format(end)
        ) {
            return `${dateFormat.format(start)}, ganztägig`;
        }

        return `${dateFormat.format(start)} – ${dateFormat.format(end)}`;
    }

    if (!end) {
        return `${dateFormat.format(start)}, ${timeFormat.format(start)}`;
    }

    if (shortDateFormat.format(start) === shortDateFormat.format(end)) {
        return `${dateFormat.format(start)}, ${timeFormat.format(start)}–${timeFormat.format(end)}`;
    }

    return `${formatDateTime(startsAt)} – ${formatDateTime(endsAt)}`;
}

/** Wert für <input type="datetime-local"> bzw. type="date" in Schweizer Zeit. */
export function toInputValue(
    value: string | null | undefined,
    withTime = true,
): string {
    if (!value) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    })
        .format(new Date(value))
        .replace(' ', 'T');

    return withTime ? parts.slice(0, 16) : parts.slice(0, 10);
}

export function nowInputValue(): string {
    return toInputValue(new Date().toISOString());
}

export function formatFileSize(bytes: number | null | undefined): string {
    if (!bytes) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

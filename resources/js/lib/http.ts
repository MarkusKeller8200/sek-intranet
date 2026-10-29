/** POST mit JSON an eine Route des Intranets (CSRF über das XSRF-TOKEN-Cookie). */
export async function postJson<T>(url: string, body: unknown = {}): Promise<T> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(body),
    });

    const data: unknown = await response.json().catch(() => ({
        status: 'error',
        message: `HTTP ${response.status}`,
    }));

    if (!response.ok && typeof data === 'object' && data !== null) {
        const message =
            'message' in data && typeof data.message === 'string'
                ? data.message
                : `HTTP ${response.status}`;

        return { status: 'error', message } as T;
    }

    return data as T;
}

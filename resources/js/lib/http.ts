/** POST mit JSON an eine Route des Intranets (CSRF über das XSRF-TOKEN-Cookie). */
export async function postJson<T>(
    url: string,
    body: unknown = {},
    files: Record<string, File> = {},
): Promise<T> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': token,
    };
    let payload: BodyInit;

    if (Object.keys(files).length > 0) {
        // Mit Datei: multipart, übrige Angaben als JSON im Feld «payload»
        const form = new FormData();
        form.append('payload', JSON.stringify(body));
        for (const [key, file] of Object.entries(files)) {
            form.append(`file_${key}`, file);
        }
        payload = form;
    } else {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: payload,
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

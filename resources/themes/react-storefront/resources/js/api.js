// Thin CSRF-aware JSON fetch; throws on non-2xx instead of returning { ok, status, body }.

export function currentLocale() {
    return document.documentElement.lang || 'en';
}

// Reads from window.__i18n, set by layout.blade.php from the same per-request trans() calls Blade uses.
export function t(path, fallback, replace) {
    const [group, key] = path.split('.');
    const text = window.__i18n?.[group]?.[key] ?? fallback ?? path;
    return replace ? Object.keys(replace).reduce((acc, k) => acc.replaceAll(`:${k}`, String(replace[k])), text) : text;
}

function readCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

export async function apiFetch(path, options = {}) {
    const headers = Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });

    let body = null;
    try {
        body = await response.json();
    } catch {
        body = null;
    }

    if (!response.ok) {
        const error = new Error((body && body.message) || 'Request failed');
        error.status = response.status;
        error.code = body && body.code;
        error.body = body;
        throw error;
    }

    return body;
}

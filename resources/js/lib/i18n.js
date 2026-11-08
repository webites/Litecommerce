import { usePage } from '@inertiajs/react';

function resolve(translations, key) {
    const [namespace, ...rest] = key.split('.');
    let node = translations?.[namespace];
    for (const segment of rest) {
        if (node == null) return undefined;
        node = node[segment];
    }
    return typeof node === 'string' ? node : undefined;
}

function interpolate(text, replace) {
    if (!replace) return text;
    return Object.keys(replace).reduce((acc, key) => acc.replaceAll(`:${key}`, String(replace[key])), text);
}

export function useTranslate() {
    const { translations } = usePage().props;
    return (key, fallback, replace) => interpolate(resolve(translations, key) ?? fallback ?? key, replace);
}

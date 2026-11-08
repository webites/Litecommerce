import { router, usePage } from '@inertiajs/react';

const LOCALES = [
    { code: 'en', label: 'EN' },
    { code: 'pl', label: 'PL' },
];

export default function LocaleSwitcher() {
    const { locale } = usePage().props;

    return (
        <div style={{ display: 'inline-flex', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-pill)', padding: 2, gap: 2 }}>
            {LOCALES.map(({ code, label }) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => code !== locale && router.post(route('panel.locale.update'), { locale: code }, { onSuccess: () => window.location.reload() })}
                    style={{
                        border: 'none',
                        borderRadius: 'var(--radius-pill)',
                        padding: '4px 10px',
                        fontFamily: 'var(--font-body)',
                        fontSize: 12,
                        fontWeight: 600,
                        cursor: code === locale ? 'default' : 'pointer',
                        background: code === locale ? 'var(--brand-solid)' : 'transparent',
                        color: code === locale ? '#fff' : 'var(--text-secondary)',
                    }}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

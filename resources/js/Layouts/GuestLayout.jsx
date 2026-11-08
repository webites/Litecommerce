import { Link } from '@inertiajs/react';

/**
 * Shell for the pre-panel "fundament i logowanie" screens (login, password
 * flows, two-factor challenge, setup wizard) — centered card on the app
 * canvas, per the Litecommerce panel design system.
 */
export default function GuestLayout({ children, width = 380 }) {
    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: 'var(--bg-canvas)',
                fontFamily: 'var(--font-body)',
                padding: '24px 16px',
            }}
        >
            <div style={{ width: '100%', maxWidth: width, display: 'flex', flexDirection: 'column', gap: 22 }}>
                <Link
                    href="/"
                    style={{
                        fontFamily: 'var(--font-display)',
                        fontWeight: 700,
                        fontSize: 22,
                        color: 'var(--text-primary)',
                        textAlign: 'center',
                        textDecoration: 'none',
                    }}
                >
                    Litecommerce
                </Link>
                <div
                    style={{
                        background: 'var(--bg-surface)',
                        border: '1px solid var(--border-subtle)',
                        borderRadius: 'var(--radius-card)',
                        boxShadow: 'var(--shadow-sm)',
                        padding: 28,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}

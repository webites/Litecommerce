import { User } from 'lucide-react';

/**
 * Avatar primitive from the Litecommerce panel design system.
 */
export default function Avatar({ name = '', src, size = 32 }) {
    const initials = name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');

    if (src) {
        return (
            <img
                src={src}
                alt={name}
                style={{ width: size, height: size, borderRadius: '50%', objectFit: 'cover' }}
            />
        );
    }

    return (
        <div
            style={{
                width: size,
                height: size,
                borderRadius: '50%',
                background: 'var(--accent-4)',
                color: 'var(--accent-11)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontFamily: 'var(--font-display)',
                fontWeight: 600,
                fontSize: size * 0.38,
            }}
        >
            {initials || <User size={size * 0.5} />}
        </div>
    );
}

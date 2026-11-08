import { Link } from '@inertiajs/react';
import { useState } from 'react';

const sizes = {
    sm: { h: 32, px: 12, fs: 13 },
    md: { h: 38, px: 16, fs: 14 },
    lg: { h: 44, px: 20, fs: 15 },
};

const variants = {
    solid: {
        bg: 'var(--brand-solid)',
        bgHover: 'var(--brand-solid-hover)',
        bgActive: 'var(--brand-solid-active)',
        fg: '#fff',
        border: 'transparent',
    },
    soft: {
        bg: 'var(--brand-subtle-bg)',
        bgHover: 'var(--accent-3)',
        bgActive: 'var(--accent-4)',
        fg: 'var(--brand-subtle-text)',
        border: 'transparent',
    },
    outline: {
        bg: 'transparent',
        bgHover: 'var(--gray-3)',
        bgActive: 'var(--gray-4)',
        fg: 'var(--text-primary)',
        border: 'var(--border-default)',
    },
    ghost: {
        bg: 'transparent',
        bgHover: 'var(--gray-3)',
        bgActive: 'var(--gray-4)',
        fg: 'var(--text-primary)',
        border: 'transparent',
    },
    destructive: {
        bg: 'var(--danger-solid)',
        bgHover: 'var(--red-8)',
        bgActive: 'var(--red-11)',
        fg: '#fff',
        border: 'transparent',
    },
};

/**
 * Button primitive from the Litecommerce panel design system.
 * Renders an Inertia `Link` when `href` is given, otherwise a `<button>`.
 */
export default function Button({
    variant = 'solid',
    size = 'md',
    disabled = false,
    loading = false,
    children,
    onClick,
    type = 'button',
    fullWidth = false,
    href,
    className,
    icon,
    iconPosition = 'left',
    ...rest
}) {
    const v = variants[variant] || variants.solid;
    const s = sizes[size] || sizes.md;
    const [hover, setHover] = useState(false);
    const [active, setActive] = useState(false);

    const bg = disabled ? 'var(--gray-4)' : active ? v.bgActive : hover ? v.bgHover : v.bg;
    const fg = disabled ? 'var(--text-disabled)' : v.fg;

    const style = {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
        width: fullWidth ? '100%' : undefined,
        height: s.h,
        padding: `0 ${s.px}px`,
        fontFamily: 'var(--font-body)',
        fontWeight: 600,
        fontSize: s.fs,
        background: bg,
        color: fg,
        border: `1px solid ${disabled ? 'var(--border-subtle)' : v.border}`,
        borderRadius: 'var(--radius-control)',
        cursor: disabled || loading ? 'not-allowed' : 'pointer',
        transition: 'background var(--duration-fast) var(--ease-out)',
        whiteSpace: 'nowrap',
        textDecoration: 'none',
    };

    const interactionProps = {
        onMouseEnter: () => setHover(true),
        onMouseLeave: () => {
            setHover(false);
            setActive(false);
        },
        onMouseDown: () => setActive(true),
        onMouseUp: () => setActive(false),
    };

    const content = (
        <>
            {loading && (
                <span
                    style={{
                        width: 14,
                        height: 14,
                        border: '2px solid currentColor',
                        borderRightColor: 'transparent',
                        borderRadius: '50%',
                        animation: 'lc-spin .6s linear infinite',
                    }}
                />
            )}
            {icon && iconPosition === 'left' && !loading ? icon : null}
            {children}
            {icon && iconPosition === 'right' ? icon : null}
        </>
    );

    if (href && !disabled) {
        return (
            <Link href={href} onClick={onClick} className={className} style={style} {...interactionProps} {...rest}>
                {content}
            </Link>
        );
    }

    return (
        <button
            type={type}
            disabled={disabled || loading}
            onClick={onClick}
            className={className}
            style={style}
            {...interactionProps}
            {...rest}
        >
            {content}
        </button>
    );
}

import { Check, Minus } from 'lucide-react';

/**
 * Checkbox primitive from the Litecommerce panel design system.
 */
export default function Checkbox({ label, checked = false, onChange, disabled, indeterminate, name }) {
    return (
        <label
            style={{
                position: 'relative',
                display: 'inline-flex',
                alignItems: 'center',
                gap: 8,
                cursor: disabled ? 'not-allowed' : 'pointer',
                fontFamily: 'var(--font-body)',
                fontSize: 14,
                color: disabled ? 'var(--text-disabled)' : 'var(--text-primary)',
            }}
        >
            <input
                type="checkbox"
                name={name}
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange?.(e.target.checked)}
                style={{ position: 'absolute', width: 1, height: 1, opacity: 0, pointerEvents: 'none' }}
            />
            <span
                style={{
                    width: 18,
                    height: 18,
                    borderRadius: 5,
                    border: `1.5px solid ${checked || indeterminate ? 'var(--brand-solid)' : 'var(--border-strong)'}`,
                    background: checked || indeterminate ? 'var(--brand-solid)' : 'var(--bg-surface)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                    transition: 'background var(--duration-fast) var(--ease-out)',
                }}
            >
                {checked ? (
                    <Check size={13} color="#fff" />
                ) : indeterminate ? (
                    <Minus size={13} color="#fff" />
                ) : null}
            </span>
            {label}
        </label>
    );
}

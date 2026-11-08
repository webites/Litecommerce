import { useEffect, useRef, useState } from 'react';

/**
 * Dropdown menu primitive from the Litecommerce panel design system.
 * Extended with an outside-click handler (the design source only modelled
 * open/close via the trigger and item clicks).
 */
export default function DropdownMenu({ trigger, items = [] }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;

        const onPointerDown = (event) => {
            if (ref.current && !ref.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open]);

    return (
        <div ref={ref} style={{ position: 'relative', display: 'inline-block' }}>
            <div onClick={() => setOpen((v) => !v)}>{trigger}</div>
            {open && (
                <div
                    style={{
                        position: 'absolute',
                        top: 'calc(100% + 4px)',
                        right: 0,
                        minWidth: 180,
                        background: 'var(--bg-surface-raised)',
                        border: '1px solid var(--border-default)',
                        borderRadius: 'var(--radius-md)',
                        boxShadow: 'var(--shadow-md)',
                        padding: 6,
                        zIndex: 20,
                        fontFamily: 'var(--font-body)',
                    }}
                >
                    {items.map((it, i) => (
                        <div
                            key={i}
                            onClick={() => {
                                it.onClick?.();
                                setOpen(false);
                            }}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '8px 10px',
                                borderRadius: 'var(--radius-sm)',
                                cursor: 'pointer',
                                fontSize: 13,
                                color: it.danger ? 'var(--danger-text)' : 'var(--text-primary)',
                            }}
                        >
                            {it.icon}
                            {it.label}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

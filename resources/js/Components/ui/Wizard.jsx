import { Check } from 'lucide-react';
import { Fragment } from 'react';

/**
 * Step indicator pattern from the Litecommerce panel design system.
 */
export default function Wizard({ steps = [], activeIndex = 0, children }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 24, fontFamily: 'var(--font-body)' }}>
            <div style={{ display: 'flex', alignItems: 'center' }}>
                {steps.map((s, i) => (
                    <Fragment key={i}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <div
                                style={{
                                    width: 28,
                                    height: 28,
                                    flexShrink: 0,
                                    borderRadius: '50%',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 13,
                                    fontWeight: 600,
                                    background:
                                        i < activeIndex
                                            ? 'var(--success-solid)'
                                            : i === activeIndex
                                              ? 'var(--brand-solid)'
                                              : 'var(--gray-4)',
                                    color: i <= activeIndex ? '#fff' : 'var(--text-tertiary)',
                                }}
                            >
                                {i < activeIndex ? <Check size={14} /> : i + 1}
                            </div>
                            <span
                                style={{
                                    fontSize: 13,
                                    fontWeight: i === activeIndex ? 600 : 500,
                                    color: i === activeIndex ? 'var(--text-primary)' : 'var(--text-tertiary)',
                                }}
                            >
                                {steps.length > 4 && i !== activeIndex ? null : s}
                            </span>
                        </div>
                        {i < steps.length - 1 && (
                            <div
                                style={{
                                    flex: 1,
                                    height: 1,
                                    background: i < activeIndex ? 'var(--success-solid)' : 'var(--border-default)',
                                    margin: '0 4px',
                                    minWidth: 2,
                                }}
                            />
                        )}
                    </Fragment>
                ))}
            </div>
            <div>{children}</div>
        </div>
    );
}

import { Fragment } from 'react';

/**
 * Table primitive from the Litecommerce panel design system.
 */
export default function Table({ columns = [], rows = [], onRowClick }) {
    return (
        <div
            style={{
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-card)',
                position: 'relative',
                overflow: 'auto',
                background: 'var(--bg-surface)',
                fontFamily: 'var(--font-body)',
            }}
        >
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14, minWidth: 480 }}>
                <thead>
                    <tr>
                        {columns.map((c, i) => (
                            <th
                                key={i}
                                style={{
                                    textAlign: c.align || 'left',
                                    padding: '10px 16px',
                                    fontSize: 11,
                                    fontWeight: 600,
                                    textTransform: 'uppercase',
                                    letterSpacing: 'var(--tracking-wide)',
                                    color: 'var(--text-tertiary)',
                                    background: 'var(--gray-2)',
                                    borderBottom: '1px solid var(--border-subtle)',
                                }}
                            >
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r, ri) => (
                        <Fragment key={ri}>
                            <tr
                                onClick={() => onRowClick?.(r)}
                                style={{
                                    cursor: onRowClick ? 'pointer' : 'default',
                                    borderBottom: ri < rows.length - 1 ? '1px solid var(--border-subtle)' : 'none',
                                }}
                            >
                                {columns.map((c, ci) => (
                                    <td key={ci} style={{ padding: '12px 16px', textAlign: c.align || 'left', color: 'var(--text-primary)' }}>
                                        {c.render ? c.render(r) : r[c.key]}
                                    </td>
                                ))}
                            </tr>
                        </Fragment>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length} style={{ padding: '40px 16px', textAlign: 'center', color: 'var(--text-tertiary)' }}>
                                Brak danych.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

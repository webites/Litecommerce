import { Fragment } from 'react';

/**
 * Table primitive from the Litecommerce panel design system.
 * With `cards`, a narrow screen shows each row as a card (first column as the title,
 * a column with an empty header as the action row) instead of a horizontally scrolling table.
 */
export default function Table({ columns = [], rows = [], onRowClick, cards = false }) {
    const table = (
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

    if (!cards) return table;

    const titleColumn = columns.find((c) => c.header) ?? columns[0];
    const detailColumns = columns.filter((c) => c !== titleColumn && c.header);
    const actionColumns = columns.filter((c) => !c.header && c !== titleColumn);
    const cell = (c, r) => (c.render ? c.render(r) : r[c.key]);

    return (
        <>
            <div className="hidden sm:block">{table}</div>
            <div className="flex flex-col sm:hidden" style={{ gap: 10, fontFamily: 'var(--font-body)' }}>
                {rows.map((r, ri) => (
                    <div
                        key={ri}
                        onClick={() => onRowClick?.(r)}
                        style={{
                            border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-surface)',
                            padding: 14, display: 'flex', flexDirection: 'column', gap: 10, cursor: onRowClick ? 'pointer' : 'default',
                        }}
                    >
                        <div style={{ fontSize: 15, color: 'var(--text-primary)' }}>{cell(titleColumn, r)}</div>
                        {detailColumns.map((c, ci) => (
                            <div key={ci} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, fontSize: 13 }}>
                                <span style={{ color: 'var(--text-tertiary)' }}>{c.header}</span>
                                <span style={{ color: 'var(--text-primary)', textAlign: 'right' }}>{cell(c, r)}</span>
                            </div>
                        ))}
                        {actionColumns.map((c, ci) => (
                            <div key={ci} style={{ display: 'flex', justifyContent: 'flex-end', borderTop: '1px solid var(--border-subtle)', paddingTop: 10 }}>{cell(c, r)}</div>
                        ))}
                    </div>
                ))}
                {rows.length === 0 && <div style={{ padding: '24px 16px', textAlign: 'center', color: 'var(--text-tertiary)' }}>Brak danych.</div>}
            </div>
        </>
    );
}

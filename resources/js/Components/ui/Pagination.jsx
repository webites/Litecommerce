import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const buttonStyle = (disabled) => ({
    width: 32,
    height: 32,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 'var(--radius-control)',
    border: '1px solid var(--border-default)',
    background: 'var(--bg-surface)',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.4 : 1,
    color: 'var(--text-secondary)',
    textDecoration: 'none',
});

/**
 * Pagination adapted for a Laravel `LengthAwarePaginator` payload
 * ({ current_page, last_page, prev_page_url, next_page_url }), unlike the
 * design source's client-side page/pageCount/onChange state.
 */
export default function Pagination({ paginator }) {
    const { current_page: page, last_page: lastPage, prev_page_url: prevUrl, next_page_url: nextUrl } = paginator;

    if (lastPage <= 1) return null;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontFamily: 'var(--font-body)' }}>
            {prevUrl ? (
                <Link href={prevUrl} preserveScroll style={buttonStyle(false)}>
                    <ChevronLeft size={16} />
                </Link>
            ) : (
                <span style={buttonStyle(true)}>
                    <ChevronLeft size={16} />
                </span>
            )}
            <span style={{ fontSize: 13, color: 'var(--text-secondary)', padding: '0 6px' }}>
                Strona {page} z {lastPage}
            </span>
            {nextUrl ? (
                <Link href={nextUrl} preserveScroll style={buttonStyle(false)}>
                    <ChevronRight size={16} />
                </Link>
            ) : (
                <span style={buttonStyle(true)}>
                    <ChevronRight size={16} />
                </span>
            )}
        </div>
    );
}

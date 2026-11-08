import { ChevronDown, ChevronRight, Folder } from 'lucide-react';
import { useState } from 'react';

function Node({ node, depth, expanded, onToggle, selectedId, onSelect }) {
    const isOpen = expanded.has(node.id);
    const hasChildren = node.children && node.children.length > 0;
    const selected = selectedId === node.id;

    return (
        <div>
            <div
                onClick={onSelect ? () => onSelect(node.id) : undefined}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    padding: '7px 8px',
                    paddingLeft: 8 + depth * 20,
                    borderRadius: 'var(--radius-sm)',
                    cursor: onSelect ? 'pointer' : undefined,
                    background: selected ? 'var(--brand-subtle-bg)' : 'transparent',
                }}
            >
                {hasChildren ? (
                    <span
                        onClick={(event) => {
                            event.stopPropagation();
                            onToggle(node.id);
                        }}
                        style={{ cursor: 'pointer', display: 'flex' }}
                    >
                        {isOpen ? <ChevronDown size={14} color="var(--text-tertiary)" /> : <ChevronRight size={14} color="var(--text-tertiary)" />}
                    </span>
                ) : (
                    <span style={{ width: 14 }} />
                )}
                <Folder size={15} color="var(--text-tertiary)" />
                <span style={{ fontSize: 13, color: selected ? 'var(--brand-subtle-text)' : 'var(--text-primary)' }}>{node.label}</span>
                {node.hidden && <span style={{ fontSize: 11, color: 'var(--text-tertiary)' }}>ukryta</span>}
            </div>
            {isOpen && hasChildren && node.children.map((c) => (
                <Node key={c.id} node={c} depth={depth + 1} expanded={expanded} onToggle={onToggle} selectedId={selectedId} onSelect={onSelect} />
            ))}
        </div>
    );
}

/**
 * Category tree pattern from the Litecommerce panel design system.
 * Expects pre-built `nodes` ({id, label, hidden, children}); build the
 * tree from a flat list of categories before passing it in. Pass
 * `onSelect` to make nodes clickable (e.g. to load one into an edit form).
 */
export default function CategoryTree({ nodes = [], selectedId, onSelect }) {
    const [expanded, setExpanded] = useState(() => new Set(nodes.map((n) => n.id)));
    const onToggle = (id) =>
        setExpanded((s) => {
            const n = new Set(s);
            n.has(id) ? n.delete(id) : n.add(id);
            return n;
        });

    return (
        <div style={{ fontFamily: 'var(--font-body)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', padding: 8, background: 'var(--bg-surface)' }}>
            {nodes.map((n) => (
                <Node key={n.id} node={n} depth={0} expanded={expanded} onToggle={onToggle} selectedId={selectedId} onSelect={onSelect} />
            ))}
            {nodes.length === 0 && (
                <div style={{ padding: '16px 8px', fontSize: 13, color: 'var(--text-tertiary)' }}>Brak kategorii.</div>
            )}
        </div>
    );
}

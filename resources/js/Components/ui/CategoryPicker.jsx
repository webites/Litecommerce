import { ChevronDown, ChevronRight, Search, Star } from 'lucide-react';
import { useMemo, useState } from 'react';
import Checkbox from './Checkbox';

function buildTree(categories) {
    const ids = new Set(categories.map((category) => category.id));
    const children = new Map();
    categories.forEach((category) => {
        const parent = category.parent_id && ids.has(category.parent_id) ? category.parent_id : null;
        if (!children.has(parent)) children.set(parent, []);
        children.get(parent).push(category);
    });

    const toNode = (category) => ({ ...category, children: (children.get(category.id) || []).map(toNode) });

    return (children.get(null) || []).map(toNode);
}

function collectIds(nodes, into = []) {
    nodes.forEach((node) => {
        into.push(node.id);
        collectIds(node.children, into);
    });

    return into;
}

function filterTree(nodes, term) {
    return nodes.flatMap((node) => {
        const children = filterTree(node.children, term);
        if (node.name.toLowerCase().includes(term) || children.length > 0) return [{ ...node, children }];

        return [];
    });
}

function Row({ node, depth, selected, primaryId, expanded, searching, onToggleExpand, onToggleSelect, onPrimary, labels }) {
    const isSelected = selected.has(node.id);
    const isOpen = searching || expanded.has(node.id);
    const hasChildren = node.children.length > 0;

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    minHeight: 34,
                    paddingLeft: 6 + depth * 22,
                    paddingRight: 8,
                    borderRadius: 'var(--radius-sm)',
                    background: isSelected ? 'var(--brand-subtle-bg)' : 'transparent',
                }}
            >
                {hasChildren ? (
                    <button
                        type="button"
                        aria-label={isOpen ? labels.collapse : labels.expand}
                        aria-expanded={isOpen}
                        onClick={() => onToggleExpand(node.id)}
                        style={{ display: 'flex', padding: 2, border: 'none', background: 'none', cursor: 'pointer', color: 'var(--text-tertiary)' }}
                    >
                        {isOpen ? <ChevronDown size={15} /> : <ChevronRight size={15} />}
                    </button>
                ) : (
                    <span style={{ width: 19, flexShrink: 0 }} />
                )}
                <Checkbox label={node.name} checked={isSelected} onChange={(checked) => onToggleSelect(node.id, checked)} />
                {isSelected && (
                    primaryId === node.id ? (
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, marginLeft: 'auto', fontSize: 12, fontWeight: 600, color: 'var(--brand-subtle-text)' }}>
                            <Star size={12} fill="currentColor" />{labels.primary}
                        </span>
                    ) : (
                        <button
                            type="button"
                            onClick={() => onPrimary(node.id)}
                            style={{ marginLeft: 'auto', padding: '2px 6px', border: 'none', background: 'none', cursor: 'pointer', fontSize: 12, color: 'var(--text-secondary)', textDecoration: 'underline' }}
                        >
                            {labels.makePrimary}
                        </button>
                    )
                )}
            </div>
            {isOpen && node.children.map((child) => (
                <Row key={child.id} node={child} depth={depth + 1} selected={selected} primaryId={primaryId} expanded={expanded} searching={searching} onToggleExpand={onToggleExpand} onToggleSelect={onToggleSelect} onPrimary={onPrimary} labels={labels} />
            ))}
        </div>
    );
}

/**
 * Hierarchical category selector. `categories` is the flat list
 * ({id, parent_id, name}); the tree is built here so it follows whatever
 * the shop has. The primary category is chosen inline among the selected ones.
 */
export default function CategoryPicker({ categories, selectedIds, primaryId, onChange, labels }) {
    const tree = useMemo(() => buildTree(categories), [categories]);
    const byId = useMemo(() => new Map(categories.map((category) => [category.id, category])), [categories]);
    const selected = useMemo(() => new Set(selectedIds), [selectedIds]);
    const [term, setTerm] = useState('');
    const [expanded, setExpanded] = useState(() => {
        const open = new Set();
        selectedIds.forEach((id) => {
            let parent = byId.get(id)?.parent_id;
            while (parent && !open.has(parent)) {
                open.add(parent);
                parent = byId.get(parent)?.parent_id;
            }
        });
        if (categories.length <= 40) collectIds(tree).forEach((id) => open.add(id));

        return open;
    });

    const needle = term.trim().toLowerCase();
    const visible = needle ? filterTree(tree, needle) : tree;

    const toggleExpand = (id) => setExpanded((current) => {
        const next = new Set(current);
        if (next.has(id)) next.delete(id); else next.add(id);

        return next;
    });
    const toggleSelect = (id, checked) => {
        const ids = checked ? [...selectedIds, id] : selectedIds.filter((current) => current !== id);
        let primary = primaryId;
        if (!ids.includes(primary)) primary = ids[0] ?? '';
        onChange(ids, primary);
    };

    if (categories.length === 0) {
        return <span style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{labels.empty}</span>;
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {categories.length > 8 && (
                <div style={{ position: 'relative' }}>
                    <Search size={14} color="var(--text-tertiary)" style={{ position: 'absolute', left: 10, top: 11 }} />
                    <input
                        type="search"
                        value={term}
                        placeholder={labels.search}
                        onChange={(event) => setTerm(event.target.value)}
                        style={{ width: '100%', height: 36, padding: '0 10px 0 30px', fontSize: 13, border: '1px solid var(--border-default)', borderRadius: 'var(--radius-control)', background: 'var(--bg-surface)', color: 'var(--text-primary)' }}
                    />
                </div>
            )}
            <div style={{ maxHeight: 320, overflowY: 'auto', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-control)', padding: 6 }}>
                {visible.map((node) => (
                    <Row key={node.id} node={node} depth={0} selected={selected} primaryId={primaryId} expanded={expanded} searching={needle !== ''} onToggleExpand={toggleExpand} onToggleSelect={toggleSelect} onPrimary={(id) => onChange(selectedIds, id)} labels={labels} />
                ))}
                {visible.length === 0 && <div style={{ padding: 8, fontSize: 13, color: 'var(--text-tertiary)' }}>{labels.noMatches}</div>}
            </div>
            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                {selectedIds.length === 0 ? labels.noneSelected : labels.selectedCount.replace(':count', String(selectedIds.length))}
            </div>
        </div>
    );
}

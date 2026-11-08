import { DragDropProvider } from '@dnd-kit/react';
import { useSortable } from '@dnd-kit/react/sortable';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, GripVertical } from 'lucide-react';

/**
 * A flat, depth-annotated list rendered as an indented outline — the
 * WordPress nav-menu-editor interaction (drag to reorder, indent to nest
 * under the item above), built on @dnd-kit/react's sortable primitives.
 *
 * `items` is a flat array of `{ id, depth, ...rest }` in display order —
 * NOT a nested tree — so reordering is a plain array move; nesting is a
 * `depth` number per row (0 = top level). Menus/Edit.jsx converts to/from
 * the stored nested `children: [...]` shape at its own boundary.
 *
 * Reordering and nesting are ALSO available via up/down/indent/outdent
 * buttons on every row, not only by dragging — a pre-1.0 drag library
 * (same risk already accepted for Puck, the page builder) is not the only
 * way to use this editor.
 */
const defaultLabels = {
    outdent: 'Decrease indent',
    indent: 'Nest under item above',
    moveUp: 'Move up',
    moveDown: 'Move down',
};

export default function SortableTree({ items, onChange, renderItem, labels = defaultLabels }) {
    const move = (fromIndex, toIndex) => {
        if (toIndex < 0 || toIndex >= items.length || fromIndex === toIndex) return;
        const next = items.slice();
        const [moved] = next.splice(fromIndex, 1);
        next.splice(toIndex, 0, moved);
        onChange(normalizeDepths(next));
    };

    const indent = (index) => {
        if (index === 0) return;
        const maxDepth = items[index - 1].depth + 1;
        const next = items.slice();
        next[index] = { ...next[index], depth: Math.min(next[index].depth + 1, maxDepth) };
        onChange(normalizeDepths(next));
    };

    const outdent = (index) => {
        if (items[index].depth === 0) return;
        const next = items.slice();
        next[index] = { ...next[index], depth: next[index].depth - 1 };
        onChange(normalizeDepths(next));
    };

    const handleDragEnd = (event) => {
        if (event.canceled) return;
        // dnd-kit's default OptimisticSortingPlugin already reorders the
        // dragged item's OWN sortable.index live, as you drag — by drop
        // time, event.operation.source.index IS its final resting index,
        // and event.operation.target ends up referring to that SAME item
        // (not "the item it was dropped onto"), so comparing ids here would
        // always find them equal and never move anything.
        const sourceId = event.operation.source?.id;
        const newIndex = event.operation.source?.index;
        if (sourceId == null || newIndex == null) return;
        const fromIndex = items.findIndex((item) => item.id === sourceId);
        if (fromIndex === -1 || fromIndex === newIndex) return;
        move(fromIndex, newIndex);
    };

    return (
        <DragDropProvider onDragEnd={handleDragEnd}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {items.map((item, index) => (
                    <Row
                        key={item.id}
                        item={item}
                        index={index}
                        isFirst={index === 0}
                        isLast={index === items.length - 1}
                        onMoveUp={() => move(index, index - 1)}
                        onMoveDown={() => move(index, index + 1)}
                        onIndent={() => indent(index)}
                        onOutdent={() => outdent(index)}
                        labels={labels}
                    >
                        {renderItem(item, index)}
                    </Row>
                ))}
            </div>
        </DragDropProvider>
    );
}

function Row({ item, index, isFirst, isLast, onMoveUp, onMoveDown, onIndent, onOutdent, labels, children }) {
    const { ref, handleRef, isDragging } = useSortable({ id: item.id, index });

    return (
        <div
            ref={ref}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                marginLeft: item.depth * 28,
                opacity: isDragging ? 0.5 : 1,
                background: 'var(--bg-surface)',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-control)',
                padding: '8px 10px',
            }}
        >
            <span ref={handleRef} style={{ cursor: 'grab', display: 'flex', color: 'var(--text-tertiary)', touchAction: 'none' }}>
                <GripVertical size={16} />
            </span>
            <div style={{ display: 'flex', gap: 2 }}>
                <IconButton onClick={onOutdent} disabled={item.depth === 0} label={labels.outdent}>
                    <ChevronLeft size={14} />
                </IconButton>
                <IconButton onClick={onIndent} disabled={isFirst} label={labels.indent}>
                    <ChevronRight size={14} />
                </IconButton>
                <IconButton onClick={onMoveUp} disabled={isFirst} label={labels.moveUp}>
                    <ChevronUp size={14} />
                </IconButton>
                <IconButton onClick={onMoveDown} disabled={isLast} label={labels.moveDown}>
                    <ChevronDown size={14} />
                </IconButton>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>{children}</div>
        </div>
    );
}

function IconButton({ onClick, disabled, label, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: 24,
                height: 24,
                border: '1px solid var(--border-subtle)',
                borderRadius: 6,
                background: 'var(--bg-surface)',
                color: disabled ? 'var(--text-disabled)' : 'var(--text-secondary)',
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {children}
        </button>
    );
}

/** An item can never be nested deeper than one level below whatever now precedes it — the same clamp real outline editors apply after a move. */
function normalizeDepths(items) {
    let previousDepth = -1;
    return items.map((item) => {
        const depth = Math.min(item.depth, previousDepth + 1);
        previousDepth = depth;
        return { ...item, depth };
    });
}

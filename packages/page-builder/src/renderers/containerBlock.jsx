import React from 'react';

/** Mirrors views/blocks/container.blade.php's class names/structure exactly — see genericBlock.jsx's docblock for why. */
export function ContainerBlockRenderer(props) {
    const columns = Math.max(1, Math.min(4, Number(props.columns) || 2));
    const slots = [props.column_1, props.column_2, props.column_3, props.column_4].slice(0, columns);

    return (
        <div
            className={`cms-container cms-container--${props.layout ?? 'flex'} cms-container--gap-${props.gap ?? 'md'} cms-container--ratio-${props.ratio ?? 'equal'}`}
            style={{ '--cms-container-columns': columns }}
        >
            {slots.map((Slot, index) => (
                <div className="cms-container-column" key={index}>
                    {Slot ? <Slot /> : null}
                </div>
            ))}
        </div>
    );
}

import { useEffect, useState } from 'react';

/**
 * Fetches and mounts plugin-rendered fragments for one named panel slot —
 * the React-panel counterpart to a Blade hook point, since plugin code
 * cannot inject a component into the built panel bundle. See
 * Extensions\Contracts\PanelSlotRegistry and "Rozruch i rozszerzalność"
 * in docs/knowledge-base/08-extensions.md. Renders nothing when no
 * enabled plugin has registered a fragment for this slot.
 *
 * Fragment HTML is trusted as-is (dangerouslySetInnerHTML) — plugin PHP
 * is already trusted code with full framework access, so sanitizing its
 * own rendered output here would not raise the actual trust boundary.
 */
export default function PanelSlot({ name, params = {} }) {
    const [fragments, setFragments] = useState([]);
    const paramsKey = JSON.stringify(params);

    useEffect(() => {
        let cancelled = false;
        fetch(route('panel.extensions.slots.show', { slot: name, ...params }))
            .then((response) => (response.ok ? response.json() : { fragments: [] }))
            .then((data) => {
                if (!cancelled) setFragments(data.fragments ?? []);
            })
            .catch(() => {
                if (!cancelled) setFragments([]);
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [name, paramsKey]);

    if (fragments.length === 0) return null;

    return (
        <>
            {fragments.map((fragment) => (
                <div key={fragment.extension_id} dangerouslySetInnerHTML={{ __html: fragment.html }} />
            ))}
        </>
    );
}

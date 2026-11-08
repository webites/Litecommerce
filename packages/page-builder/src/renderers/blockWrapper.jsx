import React from 'react';

/** Puck's config.root.render — wraps the canvas in the real theme's own .sf-main (width/padding come from the injected theme.css, see PageBuilderEditor.jsx), mirroring theme::layout's structure around @yield('content'). */
export function SfMainRoot({ children }) {
    return <div className="sf-main">{children}</div>;
}

/** Mirrors Cms\Infrastructure\ThemeBlockRenderer::renderBlocks()'s own wrapping — same class names, so "full width" previews consistently with the real storefront render. */
export function withBlockWrapper(Render) {
    return function WrappedBlock(props) {
        return (
            <div className={`cms-block cms-block--${props.width === 'full' ? 'full' : 'contained'}`}>
                <Render {...props} />
            </div>
        );
    };
}

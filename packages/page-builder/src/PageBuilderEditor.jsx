import React, { useEffect } from 'react';
import { Puck } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { buildPuckConfig } from './buildConfig.js';

const EMPTY_DATA = { content: [], root: { props: {} } };

/**
 * Puck copies the host page's <html data-theme> and stylesheets into its
 * canvas iframe by default (syncHostStyles) — for us that means the PANEL's
 * own dark-mode preference and Tailwind bundle, not the active theme's
 * theme.css, so the preview never looked like the real storefront. This
 * override (paired with iframe={{ syncHostStyles: false }} below) replaces
 * that with the real theme stylesheet and forces the light variant, since a
 * storefront page never adopts the merchant's personal panel appearance.
 */
function createIframeStyleOverride(themeCssUrl) {
    return function IframeStyleOverride({ children, document: iframeDocument }) {
        useEffect(() => {
            if (!iframeDocument) return;

            iframeDocument.documentElement.removeAttribute('data-theme');

            if (!themeCssUrl) return;

            const link = iframeDocument.createElement('link');
            link.rel = 'stylesheet';
            link.href = themeCssUrl;
            iframeDocument.head.appendChild(link);

            return () => link.remove();
        }, [iframeDocument]);

        return children;
    };
}

/** Same reasoning as the iframe override, for the PANEL page itself: Outline/sidebar text inherits the panel's dark-mode text color via normal CSS cascade, unreadable against Puck's own light chrome — the builder always renders in light mode regardless of the admin's personal panel preference. */
function useForcedLightPanelTheme() {
    useEffect(() => {
        const html = document.documentElement;
        const previous = html.getAttribute('data-theme');
        html.setAttribute('data-theme', 'light');

        return () => {
            if (previous === null) {
                html.removeAttribute('data-theme');
            } else {
                html.setAttribute('data-theme', previous);
            }
        };
    }, []);
}

export function PageBuilderEditor({ blocks, rootFields, endpoints, themeCssUrl, data, onPublish }) {
    useForcedLightPanelTheme();
    const config = buildPuckConfig(blocks, rootFields, endpoints);

    return (
        <Puck
            config={config}
            data={data ?? EMPTY_DATA}
            onPublish={onPublish}
            iframe={{ syncHostStyles: false }}
            overrides={{ iframe: createIframeStyleOverride(themeCssUrl) }}
        />
    );
}

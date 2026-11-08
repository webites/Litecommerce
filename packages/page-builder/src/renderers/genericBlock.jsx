import React from 'react';

/**
 * Mirrors the real theme Blade partials' class names 1:1 (see
 * resources/themes/default-storefront/views/blocks/*.blade.php) — the
 * canvas iframe loads the ACTUAL active theme's theme.css (see
 * PageBuilderEditor.jsx), so reusing its classes here is what makes the
 * in-editor preview actually look like the storefront, not a
 * separately-styled approximation.
 */
export function createGenericBlockRenderer(type) {
    return function GenericBlockRenderer(props) {
        switch (type) {
            case 'hero':
                return (
                    <section
                        className={`cms-hero cms-hero--${props.text_theme ?? 'light'}`}
                        style={props.background?.url ? { backgroundImage: `url(${props.background.url})` } : undefined}
                    >
                        {props.title && <h1>{props.title}</h1>}
                        {props.subtitle && <p>{props.subtitle}</p>}
                        {props.cta_label && props.cta_url && (
                            <a className="btn btn-primary" href={props.cta_url} onClick={(e) => e.preventDefault()}>
                                {props.cta_label}
                            </a>
                        )}
                    </section>
                );
            case 'rich-text':
                // Puck pre-wraps every "richtext"-typed field value into an already-
                // rendered element (see its own useRichtextProps()) before it reaches
                // this component — render it as a child, not dangerouslySetInnerHTML
                // (which would stringify that element into "[object Object]").
                return <div className="cms-rich-text">{props.html}</div>;
            case 'image-banner':
                return props.image?.url ? (
                    <figure className="cms-image-banner">
                        <img src={props.image.url} alt={props.alt ?? ''} />
                        {props.caption && <figcaption>{props.caption}</figcaption>}
                    </figure>
                ) : (
                    <EmptyBlockPlaceholder label="Brak obrazu" />
                );
            case 'heading': {
                const Tag = `h${Math.max(1, Math.min(4, Number(props.level) || 2))}`;
                return props.text ? <Tag className="cms-heading">{props.text}</Tag> : <EmptyBlockPlaceholder label="Nagłówek" />;
            }
            case 'list': {
                const ListTag = props.style === 'ordered' ? 'ol' : 'ul';
                const items = Array.isArray(props.items) ? props.items : [];
                return items.length > 0 ? (
                    <ListTag className="cms-list">
                        {items.map((item, index) => <li key={index}>{item.text ?? ''}</li>)}
                    </ListTag>
                ) : (
                    <EmptyBlockPlaceholder label="Lista" />
                );
            }
            case 'button':
                return props.label && props.url ? (
                    <div className="cms-button">
                        <a
                            className={`btn btn-${props.style ?? 'primary'}`}
                            href={props.url}
                            onClick={(e) => e.preventDefault()}
                            target={props.open_in_new_tab || undefined}
                        >
                            {props.label}
                        </a>
                    </div>
                ) : (
                    <EmptyBlockPlaceholder label="Przycisk" />
                );
            case 'video': {
                const youtubeId = resolveYoutubeId(props.url ?? '');
                return youtubeId ? (
                    <div className="cms-video">
                        <div className="cms-video-frame">
                            <iframe
                                src={`https://www.youtube-nocookie.com/embed/${youtubeId}`}
                                title={props.caption || 'YouTube'}
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowFullScreen
                            />
                        </div>
                        {props.caption && <p className="cms-video-caption">{props.caption}</p>}
                    </div>
                ) : (
                    <EmptyBlockPlaceholder label="Wideo YouTube — wklej link" />
                );
            }
            case 'quote':
                return props.text ? (
                    <blockquote className="cms-quote">
                        <p>{props.text}</p>
                        {props.citation && <cite>— {props.citation}</cite>}
                    </blockquote>
                ) : (
                    <EmptyBlockPlaceholder label="Cytat" />
                );
            case 'separator':
                return <hr className="cms-separator" />;
            case 'spacer':
                return <div className={`cms-spacer cms-spacer--${props.height ?? 'md'}`} />;
            case 'archive-heading':
                // Its real title/description come from whatever category/brand is
                // being viewed (Cms\Infrastructure\ThemeBlockRenderer's render-time
                // context) — the builder has no such thing to preview, so it always
                // shows a placeholder here, never "[object Object]" or a blank block.
                return (
                    <div className="cms-archive-heading">
                        <h1>Nazwa kategorii / marki</h1>
                        {props.show_description && <div className="cms-archive-heading-description">Opis kategorii / marki (jeśli uzupełniony)</div>}
                    </div>
                );
            case 'product-grid':
                return (
                    <section className="cms-product-grid">
                        <div className="product-grid">
                            {Array.from({ length: Math.max(1, Math.min(4, Number(props.limit) || 4)) }).map((_, index) => (
                                <div className="product-card" key={index}>
                                    <div className="product-photo">
                                        <div className="no-image-thumb">Produkt</div>
                                    </div>
                                    <div className="product-body">
                                        <div className="product-name">Nazwa produktu</div>
                                        <div className="product-price-row">
                                            <span className="price-current mono">—</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                );
            case 'blog-post-list':
                return (
                    <div className={`blog-post-list blog-post-list--${props.layout ?? 'grid'} ${(props.layout ?? 'grid') === 'grid' ? 'blog-grid' : ''}`}>
                        {Array.from({ length: Math.max(1, Math.min(6, Number(props.limit) || 3)) }).map((_, index) => (
                            <article className="blog-card" key={index}>
                                <div className="blog-card__image-link"><div className="no-image-thumb">Obraz wpisu</div></div>
                                <div className="blog-card__body">
                                    <time>Data publikacji</time>
                                    <h2><a href="#" onClick={(event) => event.preventDefault()}>Tytuł wpisu</a></h2>
                                    {props.show_excerpt !== '0' && <p>Zajawka wpisu blogowego.</p>}
                                </div>
                            </article>
                        ))}
                    </div>
                );
            case 'blog-category-list':
                return (
                    <nav className={`blog-category-list blog-category-list--${props.layout ?? 'list'}`}>
                        {['Poradniki', 'Nowości', 'Inspiracje'].map((category) => <a href="#" onClick={(event) => event.preventDefault()} key={category}>{category}</a>)}
                    </nav>
                );
            case 'blog-post-content':
                return (
                    <article className="blog-post">
                        <header className="blog-post__header">
                            <div className="blog-post__categories"><a href="#" onClick={(event) => event.preventDefault()}>Kategoria</a></div>
                            <time>Data publikacji</time>
                            <h1>Tytuł bieżącego wpisu</h1>
                            {props.show_excerpt !== '0' && <p>Zajawka bieżącego wpisu.</p>}
                        </header>
                        {props.show_featured_image !== '0' && <div className="blog-post__featured-image no-image-thumb">Obraz wyróżniający</div>}
                        <div className="blog-post__content"><p>Treść wpisu jest pobierana dynamicznie na stronie bloga.</p></div>
                    </article>
                );
            default:
                return <EmptyBlockPlaceholder label={type} />;
        }
    };
}

/** Mirrors Cms\Infrastructure\ThemeBlockRenderer::resolveYoutubeId() exactly, so the editor preview embeds the same video the storefront will. */
function resolveYoutubeId(url) {
    const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);

    return match ? match[1] : null;
}

function EmptyBlockPlaceholder({ label }) {
    return (
        <div style={{ padding: 24, textAlign: 'center', color: '#94a3b8', border: '1px dashed #cbd5e1', borderRadius: 8 }}>
            {label}
        </div>
    );
}

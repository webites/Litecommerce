import { useEffect, useRef, useState } from 'react';
import { useTranslate } from '@/lib/i18n';
import Button from '../Button';
import Input from '../Input';
import Modal from '../Modal';

const escapeAttribute = (value) => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const withoutOrigin = (value) => value.split(window.location.origin).join('');

export const imageHtml = (media, alt) => {
    const attributes = [`src="${escapeAttribute(withoutOrigin(media.url))}"`, `alt="${escapeAttribute(alt)}"`];
    if (media.srcset) attributes.push(`srcset="${escapeAttribute(withoutOrigin(media.srcset))}"`, 'sizes="100vw"');
    if (media.width && media.height) attributes.push(`width="${media.width}"`, `height="${media.height}"`);

    return `<img ${attributes.join(' ')} loading="lazy">`;
};

export default function MediaLibraryDialog({ open, onClose, onSelect }) {
    const t = useTranslate();
    const fileInput = useRef(null);
    const [items, setItems] = useState([]);
    const [nextBefore, setNextBefore] = useState(null);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [selected, setSelected] = useState(null);
    const [alt, setAlt] = useState('');

    const load = async ({ before = null, append = false, query = search } = {}) => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await window.axios.get(route('panel.media.index'), { params: { before: before ?? undefined, search: query || undefined } });
            setItems((current) => (append ? [...current, ...data.items] : data.items));
            setNextBefore(data.next_before);
        } catch {
            setError(t('app.media_picker.load_error', 'The media library could not be loaded.'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) return;
        setSelected(null);
        setAlt('');
        setSearch('');
        load({ query: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const choose = (media) => {
        setSelected(media);
        setAlt(media.alt ?? '');
    };

    const upload = async (file) => {
        setUploading(true);
        setError(null);
        const body = new FormData();
        body.append('image', file);
        try {
            const { data } = await window.axios.post(route('panel.media.store'), body);
            setItems((current) => [data, ...current]);
            choose(data);
        } catch (failure) {
            setError(failure.response?.data?.errors?.image?.[0] ?? t('app.media_picker.upload_error', 'The image could not be uploaded.'));
        } finally {
            setUploading(false);
            if (fileInput.current) fileInput.current.value = '';
        }
    };

    return (
        <Modal show={open} onClose={onClose} title={t('app.media_picker.title', 'Media library')} maxWidth={760}>
            <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
                <form onSubmit={(event) => { event.preventDefault(); load(); }} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <Input style={{ flex: 1, minWidth: 160 }} label={t('app.media_picker.search', 'Search by file name')} value={search} onChange={(event) => setSearch(event.target.value)} />
                    <Button type="submit" variant="outline">{t('app.media_picker.search_button', 'Search')}</Button>
                    <Button type="button" loading={uploading} onClick={() => fileInput.current?.click()}>{t('app.media_picker.upload', 'Upload image')}</Button>
                    <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden onChange={(event) => { const file = event.target.files?.[0]; if (file) upload(file); }} />
                </form>
                {error && <div role="alert" style={{ color: 'var(--danger-text)', fontSize: 13 }}>{error}</div>}
                {items.length === 0 && !loading && <div style={{ color: 'var(--text-secondary)', fontSize: 13 }}>{t('app.media_picker.empty', 'No images found.')}</div>}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))', gap: 10 }}>
                    {items.map((media) => (
                        <button
                            key={media.id}
                            type="button"
                            title={media.filename}
                            aria-label={media.filename}
                            aria-pressed={selected?.id === media.id}
                            onClick={() => choose(media)}
                            style={{ padding: 0, aspectRatio: '1', overflow: 'hidden', borderRadius: 8, cursor: 'pointer', background: 'var(--gray-2)', border: selected?.id === media.id ? '2px solid var(--brand-solid)' : '1px solid var(--border-subtle)' }}
                        >
                            <img src={media.thumbnail_url ?? media.url} alt="" loading="lazy" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
                        </button>
                    ))}
                </div>
                {nextBefore !== null && <div><Button type="button" variant="outline" loading={loading} onClick={() => load({ before: nextBefore, append: true })}>{t('app.media_picker.load_more', 'Load more')}</Button></div>}
                <Input label={t('app.media_picker.alt', 'Alternative text')} helpText={t('app.media_picker.alt_help', 'Describes the image for screen readers and search engines.')} value={alt} disabled={selected === null} onChange={(event) => setAlt(event.target.value)} />
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                    <Button type="button" variant="outline" onClick={onClose}>{t('app.media_picker.cancel', 'Cancel')}</Button>
                    <Button type="button" disabled={selected === null} onClick={() => { onSelect(selected, alt); onClose(); }}>{t('app.media_picker.insert', 'Insert image')}</Button>
                </div>
            </div>
        </Modal>
    );
}

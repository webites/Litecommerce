import Button from '@/Components/ui/Button';
import Modal from '@/Components/ui/Modal';
import { useTranslate } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Shared across every screen that can change the slug of an already-published
 * resource (CMS page, blog post, blog category) — offers the 301 instead of
 * creating it silently, per docs/knowledge-base/09-presentation.md's redirects
 * section. Mirrors the promptHomepageReset flash+modal pattern already used
 * by Themes/Show.jsx (Panel\Themes\ThemeController::activate()).
 */
export default function RedirectSuggestionModal() {
    const t = useTranslate();
    const suggestion = usePage().props.flash?.redirectSuggestion;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (suggestion) setOpen(true);
    }, [suggestion]);

    if (!suggestion) return null;

    const create = () => {
        setBusy(true);
        router.post(route('panel.redirects.store'), {
            locale: suggestion.locale,
            from_path: suggestion.from_path,
            to_path: suggestion.to_path,
        }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setOpen(false); },
        });
    };

    return (
        <Modal show={open} onClose={() => setOpen(false)} title={t('store.redirect_suggestion.title', 'Create a 301 redirect?')} maxWidth={480}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <p style={{ margin: 0, fontSize: 14, color: 'var(--text-secondary)' }}>
                    {t('store.redirect_suggestion.body_prefix', 'The address changed from')} <code>{suggestion.from_path}</code> {t('store.redirect_suggestion.body_to', 'to')} <code>{suggestion.to_path}</code>. {t('store.redirect_suggestion.body_suffix', 'Without a redirect, the old address will stop working for visitors and search engines.')}
                </p>
                <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                    <Button variant="outline" onClick={() => setOpen(false)}>{t('store.redirect_suggestion.decline', 'No, thanks')}</Button>
                    <Button variant="solid" loading={busy} onClick={create}>{t('store.redirect_suggestion.confirm', 'Yes, create the redirect')}</Button>
                </div>
            </div>
        </Modal>
    );
}

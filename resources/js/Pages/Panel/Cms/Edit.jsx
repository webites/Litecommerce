import { useTranslate } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { PageBuilderEditor } from '@litecommerce/page-builder';
import { ArrowLeft, Braces } from 'lucide-react';
import { useState } from 'react';
import Button from '@/Components/ui/Button';
import RedirectSuggestionModal from '@/Components/ui/RedirectSuggestionModal';
import DynamicTagsModal from './DynamicTagsModal';
import LanguageSwitcher from './LanguageSwitcher';

export default function Edit({
    blocks, rootFields, endpoints, themeCssUrl, saveUrl, backUrl, backLabel, editUrl,
    availableTags, generalTags, languages, currentLocale, isUntranslated, data,
}) {
    const t = useTranslate();
    const title = data?.root?.props?.title || t('cms.pages.edit_title_fallback', 'Edit page');
    const [tagsModalOpen, setTagsModalOpen] = useState(false);
    const hasAnyTags = (availableTags && Object.keys(availableTags).length > 0) || (generalTags && Object.keys(generalTags).length > 0);
    const currentLanguageName = languages?.find((language) => language.code === currentLocale)?.name ?? currentLocale;

    const save = (document) => {
        const url = currentLocale ? `${saveUrl}${saveUrl.includes('?') ? '&' : '?'}locale=${currentLocale}` : saveUrl;
        router.put(url, document, { preserveScroll: true, preserveState: true });
    };

    const switchLocale = (locale) => {
        router.get(editUrl, { locale }, { preserveState: false, replace: true });
    };

    return (
        <div style={{ height: '100vh', display: 'flex', flexDirection: 'column', background: 'var(--bg-canvas)' }}>
            <Head title={t('cms.pages.edit_head_title', 'Edit: :title', { title })} />

            <header
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 16,
                    padding: '10px 16px',
                    borderBottom: '1px solid var(--border-subtle)',
                    background: 'var(--bg-surface)',
                    flexShrink: 0,
                }}
            >
                <Link
                    href={backUrl}
                    style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, fontWeight: 600, color: 'var(--text-secondary)', textDecoration: 'none' }}
                >
                    <ArrowLeft size={16} /> {backLabel}
                </Link>
                <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{title}</span>
                {editUrl && <LanguageSwitcher languages={languages} currentLocale={currentLocale} onChange={switchLocale} />}
                {hasAnyTags && (
                    <div style={{ marginLeft: 'auto' }}>
                        <Button variant="outline" size="sm" icon={<Braces size={14} />} onClick={() => setTagsModalOpen(true)}>
                            {t('cms.pages.tags_button', 'Dynamic tags')}
                        </Button>
                    </div>
                )}
            </header>

            {isUntranslated && (
                <div style={{ padding: '8px 16px', background: 'var(--warning-subtle-bg)', color: 'var(--warning-text)', fontSize: 13, flexShrink: 0 }}>
                    {t(
                        'cms.pages.untranslated_warning',
                        'This page has no version in ":language" yet — the default language content is shown below. Save to create a translation.',
                        { language: currentLanguageName },
                    )}
                </div>
            )}

            <div style={{ flex: 1, minHeight: 0 }}>
                <PageBuilderEditor blocks={blocks} rootFields={rootFields} endpoints={endpoints} themeCssUrl={themeCssUrl} data={data} onPublish={save} />
            </div>

            <DynamicTagsModal
                show={tagsModalOpen}
                onClose={() => setTagsModalOpen(false)}
                contextTags={availableTags}
                generalTags={generalTags}
            />
            <RedirectSuggestionModal />
        </div>
    );
}

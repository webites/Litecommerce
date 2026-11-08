import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Select from '@/Components/ui/Select';
import PageHeader from '@/Components/ui/PageHeader';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Index({ sitemapUrl, robotsUrl, defaultVariantSeoMode, variantSeoModes, embedded = false }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const form = useForm({ default_variant_seo_mode: defaultVariantSeoMode });
    const submit = (event) => {
        event.preventDefault();
        form.put(route('panel.catalog.seo-settings.update'));
    };

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('store.seo.page_title', 'SEO')} description={t('store.seo.page_description', 'Mapa strony i podstawowe ustawienia widoczności sklepu w wyszukiwarkach.')} />}>
            <Head title={t('store.seo.page_title', 'SEO')} />
            <div style={{ padding: 24, maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}
                <Card title={t('store.seo.sitemap.title', 'Mapa strony (sitemap)')} description={t('store.seo.sitemap.description', 'Generowana automatycznie, osobno dla każdego włączonego i niewykluczonego z indeksowania języka.')}>
                    <p style={{ fontSize: 14 }}>
                        <a href={sitemapUrl} target="_blank" rel="noreferrer">{sitemapUrl}</a>
                    </p>
                    <p style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                        {t('store.seo.sitemap.note_prefix', 'Ten adres jest też wpisany w')} <a href={robotsUrl} target="_blank" rel="noreferrer">robots.txt</a> {t('store.seo.sitemap.note_suffix', '— nie trzeba go nigdzie dodatkowo zgłaszać, choć zgłoszenie w Google Search Console przyspiesza pierwsze zaindeksowanie.')}
                    </p>
                </Card>
                <Card title={t('store.seo.variants.title', 'Indeksowanie wariantów — domyślne dla całego katalogu')} description={t('store.seo.variants.description', 'Dotyczy stron pojedynczych wariantów (kolor/rozmiar itd.). Każdy produkt może to nadpisać osobno w swojej edycji — to ustawienie działa tylko dla produktów bez własnego nadpisania.')}>
                    <form onSubmit={submit} style={{ display: 'flex', alignItems: 'flex-end', gap: 16, flexWrap: 'wrap' }}>
                        <div style={{ minWidth: 280 }}>
                            <Select
                                label={t('store.seo.variants.default_mode_label', 'Domyślny tryb')}
                                value={form.data.default_variant_seo_mode}
                                options={variantSeoModes}
                                error={form.errors.default_variant_seo_mode}
                                onChange={(event) => form.setData('default_variant_seo_mode', event.target.value)}
                            />
                        </div>
                        <Button type="submit" loading={form.processing}>{t('store.seo.variants.save', 'Zapisz')}</Button>
                    </form>
                </Card>
                <Card title={t('store.seo.where.title', 'Indeksowanie — gdzie czego szukać')} description={t('store.seo.where.description', 'Widoczność w wyszukiwarkach ustawia się w trzech miejscach, zależnie od zakresu:')}>
                    <ul style={{ fontSize: 14, paddingLeft: 20, margin: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
                        <li>
                            {t('store.seo.where.language_scope_prefix', 'Cały język sklepu (np. wyłączenie niedokończonej wersji EN) —')} <a href="#languages">{t('store.seo.where.languages_tab_label', 'zakładka "Języki"')}</a>.
                        </li>
                        <li>
                            {t('store.seo.where.variants_default', 'Domyślny tryb wariantów dla całego katalogu — powyżej na tej stronie.')}
                        </li>
                        <li>
                            {t('store.seo.where.variants_override', 'Nadpisanie trybu wariantów dla JEDNEGO produktu — pole "Indeksowanie wariantów" w edycji tego produktu.')}
                        </li>
                    </ul>
                </Card>
            </div>
        </SettingsPageFrame>
    );
}

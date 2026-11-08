import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import { useTranslate } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

export default function PageForm({ form, languages, statuses, onSubmit, submitLabel }) {
    const t = useTranslate();

    return (
        <form onSubmit={onSubmit} style={{ maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
            <Card
                title={t('cms.page_form.basic_info_title', 'Basic information')}
                description={t('cms.page_form.basic_info_description', 'Content is saved separately for the selected language.')}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
                        <Select
                            id="locale"
                            label={t('cms.page_form.locale_label', 'Content language')}
                            value={form.data.locale}
                            options={languages.map((l) => ({ label: l.name, value: l.code }))}
                            error={form.errors.locale}
                            onChange={(e) => form.setData('locale', e.target.value)}
                        />
                        <Input
                            id="title"
                            label={t('cms.page_form.title_label', 'Page title')}
                            required
                            value={form.data.title}
                            error={form.errors.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                        />
                    </div>
                    <Input
                        id="slug"
                        label={t('cms.page_form.slug_label', 'Address (slug)')}
                        required
                        value={form.data.slug}
                        error={form.errors.slug}
                        onChange={(e) => form.setData('slug', e.target.value)}
                    />
                </div>
            </Card>

            <Card
                title={t('cms.page_form.seo_card_title', 'SEO')}
                description={t('cms.page_form.seo_card_description', "Optional — if left blank, the page title is used as the browser tab title.")}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Input
                        id="seo_title"
                        label={t('cms.page_form.seo_title_label', 'SEO title')}
                        value={form.data.seo_title}
                        error={form.errors.seo_title}
                        onChange={(e) => form.setData('seo_title', e.target.value)}
                    />
                    <Textarea
                        id="seo_description"
                        label={t('cms.page_form.seo_description_label', 'SEO description')}
                        rows={3}
                        value={form.data.seo_description}
                        error={form.errors.seo_description}
                        onChange={(v) => form.setData('seo_description', v)}
                    />
                </div>
            </Card>

            <Card title={t('cms.page_form.publish_card_title', 'Publication')}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Select
                        id="status"
                        label={t('cms.page_form.status_label', 'Status')}
                        value={form.data.status}
                        options={statuses}
                        error={form.errors.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    />
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        <Button type="submit" variant="solid" disabled={form.processing}>
                            {submitLabel}
                        </Button>
                        <Link href={route('panel.cms.pages.index')} style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('cms.page_form.cancel', 'Cancel')}
                        </Link>
                    </div>
                </div>
            </Card>
        </form>
    );
}

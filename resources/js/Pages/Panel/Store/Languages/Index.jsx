import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Switch from '@/Components/ui/Switch';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';

function AddLanguageForm() {
    const t = useTranslate();
    const form = useForm({ code: '', name: '', is_enabled: true, noindex: false });

    return (
        <Card title={t('store.languages.add_title', 'Add language')} description={t('store.languages.add_description', 'The code is a short technical identifier (e.g. pl, en) — it cannot be changed later.')}>
            <form onSubmit={(event) => { event.preventDefault(); form.post(route('panel.languages.store'), { onSuccess: () => form.reset() }); }}>
                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 14 }}>
                    <Input label={t('store.languages.code_label', 'Code (e.g. de)')} value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toLowerCase())} error={form.errors.code} />
                    <Input label={t('store.languages.name_label', 'Name (e.g. Deutsch)')} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                </div>
                <div style={{ marginTop: 14, display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                    <Switch label={t('store.languages.enabled_immediately', 'Enabled immediately')} checked={form.data.is_enabled} onChange={(checked) => form.setData('is_enabled', checked)} />
                    <Switch label={t('store.languages.noindex', 'Noindex')} checked={form.data.noindex} onChange={(checked) => form.setData('noindex', checked)} />
                    <Button type="submit" loading={form.processing}>{t('store.languages.add_submit', 'Add language')}</Button>
                </div>
            </form>
        </Card>
    );
}

function LanguageForm({ language }) {
    const t = useTranslate();
    const form = useForm({
        name: language.name,
        is_enabled: language.is_enabled,
        is_default: language.is_default,
        noindex: language.noindex,
    });

    return (
        <Card title={`${language.name} (${language.code})`}>
            <form onSubmit={(event) => { event.preventDefault(); form.put(route('panel.languages.update', language.code)); }}>
                <Input label={t('store.languages.name_label', 'Name')} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                <div style={{ marginTop: 14, display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                    <Switch
                        label={t('store.languages.enabled', 'Enabled')}
                        checked={form.data.is_enabled}
                        disabled={language.is_default}
                        onChange={(checked) => form.setData('is_enabled', checked)}
                    />
                    <Switch
                        label={t('store.languages.default', 'Default')}
                        checked={form.data.is_default}
                        disabled={language.is_default}
                        onChange={(checked) => form.setData('is_default', checked)}
                    />
                    <Switch
                        label={t('store.languages.noindex_full', 'Noindex (exclude from indexing and the sitemap)')}
                        checked={form.data.noindex}
                        onChange={(checked) => form.setData('noindex', checked)}
                    />
                    <Button type="submit" loading={form.processing}>{t('store.languages.save', 'Save')}</Button>
                </div>
                {language.is_default && (
                    <p style={{ marginTop: 10, fontSize: 13, color: 'var(--text-secondary)' }}>
                        {t('store.languages.default_hint', 'The default language is always enabled. To change the default language, turn on the "Default" switch for a different language.')}
                    </p>
                )}
                {form.errors.is_enabled && <p style={{ marginTop: 10, fontSize: 13, color: 'var(--danger-text)' }}>{form.errors.is_enabled}</p>}
            </form>
        </Card>
    );
}

export default function Index({ languages, embedded = false }) {
    const t = useTranslate();
    const { flash } = usePage().props;

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('store.languages.title', 'Languages')} description={t('store.languages.description', "The store's languages, their availability, and search engine indexing.")} />}>
            <Head title={t('store.languages.title', 'Languages')} />
            <div style={{ padding: 24, maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}
                <AddLanguageForm />
                {languages.map((language) => <LanguageForm key={language.code} language={language} />)}
            </div>
        </SettingsPageFrame>
    );
}

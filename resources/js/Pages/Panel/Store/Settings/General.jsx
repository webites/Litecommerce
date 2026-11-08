import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import SingleImageUpload from '@/Components/ui/SingleImageUpload';
import { useTranslate } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';

export default function General({ settings }) {
    const { flash } = usePage().props;
    const form = useForm({ store_name: settings.store_name, blog_enabled: settings.blog_enabled, products_per_page: settings.products_per_page });
    const logoForm = useForm({ logo: null });
    const t = useTranslate();
    const uploadLogo = (file) => {
        logoForm.setData('logo', file);
        logoForm.post(route('panel.settings.logo.store'), { forceFormData: true, preserveScroll: true, onFinish: () => logoForm.reset() });
    };
    return <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
        {flash?.status && <Alert tone="success">{flash.status}</Alert>}
        {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
        <Card title={t('store.settings.basic_title')} description={t('store.settings.basic_description')}>
            <form onSubmit={(event) => { event.preventDefault(); form.put(route('panel.settings.update'), { preserveScroll: true }); }} style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                <Input label={t('store.settings.store_name')} value={form.data.store_name} error={form.errors.store_name} maxLength={255} required onChange={(event) => form.setData('store_name', event.target.value)} />
                <div><div style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>{t('store.settings.logo')}</div><SingleImageUpload label={settings.logo_url ? t('store.settings.change_logo') : t('store.settings.add_logo')} removeLabel={t('store.settings.remove_logo')} imageUrl={settings.logo_url} uploading={logoForm.processing} onUpload={uploadLogo} onRemove={() => logoForm.delete(route('panel.settings.logo.destroy'), { preserveScroll: true })} />{logoForm.errors.logo && <div style={{ color: 'var(--danger-text)', fontSize: 12, marginTop: 6 }}>{logoForm.errors.logo}</div>}</div>
                <Checkbox label={t('store.settings.blog_enabled')} checked={form.data.blog_enabled} onChange={(checked) => form.setData('blog_enabled', checked)} />
                <Input type="number" label={t('store.settings.products_per_page')} helpText={t('store.settings.products_per_page_help')} value={form.data.products_per_page} error={form.errors.products_per_page} min={1} max={100} required onChange={(event) => form.setData('products_per_page', event.target.value)} />
                <div><Button type="submit" loading={form.processing}>{t('store.settings.save')}</Button></div>
            </form>
        </Card>
    </div>;
}

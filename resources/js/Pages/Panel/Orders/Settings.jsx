import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Settings({ number_prefix: numberPrefix, next_number: nextNumber, embedded = false }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const form = useForm({ number_prefix: numberPrefix });
    const submit = (event) => {
        event.preventDefault();
        form.put(route('panel.settings.orders.update'), { preserveScroll: true });
    };

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('orders.settings.title', 'Orders')} description={t('orders.settings.description', 'How orders are numbered for customers and staff.')} />}>
            <Head title={t('orders.settings.title', 'Orders')} />
            <form onSubmit={submit} style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                <Card title={t('orders.settings.numbering_title', 'Order numbering')} description={t('orders.settings.numbering_description', 'Customers and staff see a short number instead of the technical identifier. A new prefix applies only to orders placed after the change.')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, maxWidth: 360 }}>
                        <Input
                            label={t('orders.settings.prefix_label', 'Number prefix')} placeholder="ORD-" maxLength={10}
                            value={form.data.number_prefix} error={form.errors.number_prefix}
                            helpText={t('orders.settings.prefix_hint', 'Up to 10 characters: letters, digits, dot, slash, dash, underscore. Leave empty for plain numbers.')}
                            onChange={(e) => form.setData('number_prefix', e.target.value)}
                        />
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('orders.settings.next_preview', 'Next order: :number', { number: `${form.data.number_prefix}${nextNumber}` })}
                        </div>
                    </div>
                </Card>
                <div><Button type="submit" loading={form.processing}>{t('orders.settings.save', 'Save')}</Button></div>
            </form>
        </SettingsPageFrame>
    );
}

import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Switch from '@/Components/ui/Switch';
import Textarea from '@/Components/ui/Textarea';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';

function AccountForm({ account, languages }) {
    const t = useTranslate();
    const form = useForm({
        beneficiary_name: account.beneficiary_name,
        account_number: account.account_number,
        instructions: account.instructions ?? {},
        enabled: account.enabled,
    });
    return (
        <Card title={`${account.name} (${account.code})`} description={t('payments.settings.no_active_account_hides_transfer')}>
            <form onSubmit={(event) => { event.preventDefault(); form.put(route('panel.payment-settings.update', account.code)); }}>
                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 14 }}>
                    <Input label={t('payments.settings.beneficiary_name')} value={form.data.beneficiary_name} onChange={(event) => form.setData('beneficiary_name', event.target.value)} error={form.errors.beneficiary_name} />
                    <Input label={t('payments.settings.account_number')} value={form.data.account_number} onChange={(event) => form.setData('account_number', event.target.value)} error={form.errors.account_number} />
                </div>
                {languages.map((language) => (
                    <Textarea key={language.code} label={t('payments.settings.instructions_for_language', 'Customer instructions — :language', { language: language.name })} value={form.data.instructions[language.code] ?? ''} onChange={(event) => form.setData('instructions', { ...form.data.instructions, [language.code]: event.target.value })} error={form.errors[`instructions.${language.code}`]} style={{ marginTop: 14 }} />
                ))}
                <div style={{ marginTop: 14, display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                    <Switch label={t('payments.settings.transfer_active')} checked={form.data.enabled} onChange={(enabled) => form.setData('enabled', enabled)} />
                    <Button type="submit" loading={form.processing}>{t('payments.settings.save_account')}</Button>
                </div>
            </form>
        </Card>
    );
}

export default function Settings({ currencies, languages, embedded = false }) {
    const { flash } = usePage().props;
    const t = useTranslate();
    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('payments.settings.title')} description={t('payments.settings.description')} />}>
            <Head title={t('payments.settings.title')} />
            <div style={{ padding: 24, maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}
                {currencies.map((account) => <AccountForm key={account.code} account={account} languages={languages} />)}
            </div>
        </SettingsPageFrame>
    );
}

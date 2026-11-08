import Radio from '@/Components/ui/Radio';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';

export default function Language({ steps, languages }) {
    const t = useTranslate();
    const { locale } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ code: languages.some((l) => l.code === locale) ? locale : languages[0]?.code });

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="language"
            title={t('setup.language.title', 'Store language')}
            description={t('setup.language.description', 'The default language of your storefront. You can add more languages later in Settings.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store')); }}
            processing={processing}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {languages.map((language) => (
                    <Radio key={language.code} name="code" label={language.name} checked={data.code === language.code} onChange={() => setData('code', language.code)} />
                ))}
                {errors.code && <div style={{ fontSize: 12, color: 'var(--danger-text)' }}>{errors.code}</div>}
            </div>
        </SetupWizardShell>
    );
}

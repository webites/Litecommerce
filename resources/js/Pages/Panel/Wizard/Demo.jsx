import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

export default function Demo({ steps, skippable }) {
    const t = useTranslate();
    const { post, processing } = useForm({});

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="demo"
            skippable={skippable}
            title={t('setup.demo.title', 'Sample data')}
            description={t('setup.demo.description', 'Add example products, categories and pages so you can see how the store looks. You can remove them with one click later in Settings → Advanced. Skip this to start with an empty store.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store')); }}
            processing={processing}
            submitLabel={t('setup.demo.install', 'Add sample data')}
        />
    );
}

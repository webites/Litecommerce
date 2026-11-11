import PageHeader from '@/Components/ui/PageHeader';
import Tabs from '@/Components/ui/Tabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Delivery from '@/Pages/Panel/Delivery/Index';
import Payments from '@/Pages/Panel/Payments/Settings';
import Orders from '@/Pages/Panel/Orders/Settings';
import Pricing from '@/Pages/Panel/Pricing/Index';
import Languages from '@/Pages/Panel/Store/Languages/Index';
import Redirects from '@/Pages/Panel/Store/Redirects/Index';
import Seo from '@/Pages/Panel/Store/Seo/Index';
import Updates from '@/Pages/Panel/Updates/Index';
import { useTranslate } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Advanced from './Advanced';
import Email from './Email';
import General from './General';
import Scheduler from './Scheduler';

const components = { general: General, advanced: Advanced, pricing: Pricing, orders: Orders, delivery: Delivery, payments: Payments, languages: Languages, seo: Seo, redirects: Redirects, email: Email, scheduler: Scheduler, updates: Updates };

export default function Index() {
    const { settingsTabs = [], flash } = usePage().props;
    const t = useTranslate();
    const initialId = useMemo(() => {
        const hash = window.location.hash.replace(/^#/, '');
        return settingsTabs.some((tab) => tab.id === hash) ? hash : settingsTabs[0]?.id;
    }, [settingsTabs]);
    const [activeId, setActiveId] = useState(initialId);
    const [content, setContent] = useState(null);
    const [loading, setLoading] = useState(true);
    const loadError = t('store.settings.load_error');

    const loadTab = useCallback((id, silent = false) => {
        if (!silent) setLoading(true);
        return fetch(route('panel.settings.tabs.show', id), { headers: { Accept: 'application/json' } })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then(setContent)
            .catch(() => setContent({ html: `<p>${loadError}</p>` }))
            .finally(() => setLoading(false));
    }, [loadError]);

    useEffect(() => {
        if (activeId) loadTab(activeId);
    }, [activeId, loadTab]);

    useEffect(() => {
        if (!activeId) return undefined;
        return router.on('success', () => loadTab(activeId, true));
    }, [activeId, loadTab]);

    useEffect(() => {
        const syncHash = () => {
            const id = window.location.hash.replace(/^#/, '');
            if (settingsTabs.some((tab) => tab.id === id)) setActiveId(id);
        };
        window.addEventListener('hashchange', syncHash);
        return () => window.removeEventListener('hashchange', syncHash);
    }, [settingsTabs]);

    const select = (id) => {
        if (flash?.status || flash?.error) router.replaceProp('flash', {}, { preserveScroll: true });
        window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#${id}`);
        setActiveId(id);
    };
    const Component = content?.component ? components[content.component] : null;

    return <AuthenticatedLayout header={<PageHeader title={t('store.settings.title')} description={t('store.settings.description')} />}>
        <Head title={t('store.settings.title')} />
        <div style={{ padding: '0 24px 24px', maxWidth: 1100 }}>
            <Tabs
                ariaLabel={t('store.settings.title')}
                items={settingsTabs.map((tab) => ({ key: tab.id, label: tab.label }))}
                value={activeId}
                onChange={select}
            />
            {loading && <div style={{ padding: 20, color: 'var(--text-secondary)' }}>{t('store.settings.loading')}</div>}
            {!loading && Component && <Component {...content.props} embedded />}
            {!loading && !Component && content?.html != null && <div dangerouslySetInnerHTML={{ __html: content.html }} />}
        </div>
    </AuthenticatedLayout>;
}

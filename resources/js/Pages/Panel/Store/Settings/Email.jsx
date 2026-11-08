import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Tabs from '@/Components/ui/Tabs';
import Textarea from '@/Components/ui/Textarea';
import { useTranslate } from '@/lib/i18n';
import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';

const sections = ['connection', 'templates', 'layouts', 'queue'];

const sectionFromUrl = () => {
    const value = new URL(window.location.href).searchParams.get('email_section');
    return sections.includes(value) ? value : 'connection';
};

export default function Email({ settings, transports, languages, definitions, layouts, statuses, messages, queueHealth }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const [section, setSection] = useState(sectionFromUrl);

    useEffect(() => {
        const syncSection = () => setSection(sectionFromUrl());
        window.addEventListener('popstate', syncSection);
        return () => window.removeEventListener('popstate', syncSection);
    }, []);

    const selectSection = (key) => {
        if (key === section) return;
        const url = new URL(window.location.href);
        url.searchParams.set('email_section', key);
        window.history.pushState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
        setSection(key);
    };

    return <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        {flash?.status && <Alert tone="success">{flash.status}</Alert>}
        {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
        <Tabs
            variant="pill"
            ariaLabel={t('email.ui.sections_label')}
            items={sections.map((key) => ({ key, label: t(`email.ui.sections.${key}`) }))}
            value={section}
            onChange={selectSection}
        />
        {section === 'connection' && <Connection settings={settings} transports={transports} statuses={statuses} t={t} />}
        {section === 'templates' && <Templates definitions={definitions} languages={languages} layouts={layouts} t={t} />}
        {section === 'layouts' && <Layouts layouts={layouts} languages={languages} t={t} />}
        {section === 'queue' && <Queue definitions={definitions} languages={languages} messages={messages} queueHealth={queueHealth} t={t} />}
    </div>;
}

function Connection({ settings, transports, statuses, t }) {
    const form = useForm({
        from_name: settings.from_name ?? '', from_email: settings.from_email ?? '', reply_to: settings.reply_to ?? '',
        transport_key: settings.transport_key ?? 'smtp', smtp_host: settings.smtp_host ?? '', smtp_port: settings.smtp_port ?? 587,
        smtp_encryption: settings.smtp_encryption ?? '', smtp_username: settings.smtp_username ?? '', smtp_password: '',
        status_notifications: settings.status_notifications ?? [],
    });
    const toggleStatus = (key, checked) => form.setData('status_notifications', checked ? [...form.data.status_notifications, key] : form.data.status_notifications.filter((value) => value !== key));
    return <Card title={t('email.ui.connection_title')} description={t('email.ui.connection_description')}>
        <form onSubmit={(event) => { event.preventDefault(); form.put(route('panel.email.update'), { preserveScroll: true }); }} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 14 }}>
                <Input label={t('email.ui.from_name')} value={form.data.from_name} error={form.errors.from_name} onChange={(event) => form.setData('from_name', event.target.value)} required />
                <Input type="email" label={t('email.ui.from_email')} value={form.data.from_email} error={form.errors.from_email} onChange={(event) => form.setData('from_email', event.target.value)} required />
                <Input type="email" label={t('email.ui.reply_to')} value={form.data.reply_to} error={form.errors.reply_to} onChange={(event) => form.setData('reply_to', event.target.value)} />
                <Select label={t('email.ui.transport')} value={form.data.transport_key} options={transports.map((value) => ({ value, label: value.toUpperCase() }))} onChange={(event) => form.setData('transport_key', event.target.value)} />
                <Input label={t('email.ui.smtp_host')} value={form.data.smtp_host} error={form.errors.smtp_host} onChange={(event) => form.setData('smtp_host', event.target.value)} required />
                <Input type="number" label={t('email.ui.smtp_port')} value={form.data.smtp_port} error={form.errors.smtp_port} onChange={(event) => form.setData('smtp_port', Number(event.target.value))} required />
                <Select label={t('email.ui.encryption')} value={form.data.smtp_encryption} options={[{ value: '', label: t('email.ui.none') }, { value: 'tls', label: 'TLS' }, { value: 'ssl', label: 'SSL' }]} onChange={(event) => form.setData('smtp_encryption', event.target.value)} />
                <Input label={t('email.ui.username')} value={form.data.smtp_username} error={form.errors.smtp_username} onChange={(event) => form.setData('smtp_username', event.target.value)} />
                <Input type="password" label={t('email.ui.password')} placeholder={settings.smtp_password_configured ? t('email.ui.password_unchanged') : ''} value={form.data.smtp_password} error={form.errors.smtp_password} onChange={(event) => form.setData('smtp_password', event.target.value)} />
            </div>
            <div><div style={{ fontWeight: 600, marginBottom: 8 }}>{t('email.ui.status_notifications')}</div><div style={{ display: 'flex', flexWrap: 'wrap', gap: 14 }}>{statuses.filter((status) => status.customer_visible).map((status) => <Checkbox key={status.key} label={status.label} checked={form.data.status_notifications.includes(status.key)} onChange={(checked) => toggleStatus(status.key, checked)} />)}</div></div>
            <div><Button type="submit" loading={form.processing}>{t('email.ui.save')}</Button></div>
        </form>
    </Card>;
}

function Templates({ definitions, languages, layouts, t }) {
    const [definitionKey, setDefinitionKey] = useState(definitions[0]?.key ?? '');
    const [locale, setLocale] = useState(languages[0] ?? 'pl');
    const definition = definitions.find((item) => item.key === definitionKey);
    const template = definition?.templates?.[locale];
    const [preview, setPreview] = useState(null);
    const form = useForm({ subject: '', html: '', enabled: true, layout_id: '' });
    useEffect(() => {
        form.setData({ subject: template?.subject ?? '', html: template?.html ?? '', enabled: template?.enabled ?? true, layout_id: template?.layout_id ?? '' });
        form.clearErrors();
    }, [definitionKey, locale]);
    if (!definition) return null;
    const submit = (event) => { event.preventDefault(); form.put(route('panel.email.templates.update', { definition: definitionKey, locale }), { preserveScroll: true }); };
    const showPreview = async () => {
        form.clearErrors();
        try {
            const response = await axios.post(route('panel.email.templates.preview', definitionKey), { subject: form.data.subject, html: form.data.html, layout_id: form.data.layout_id || null });
            setPreview(response.data);
        } catch (error) {
            form.setError('html', error.response?.data?.message ?? t('store.settings.load_error'));
        }
    };
    return <><Card title={t('email.ui.templates_title')} description={t('email.ui.templates_description')}>
        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 14 }}>
                <Select label={t('email.ui.message_type')} value={definitionKey} options={definitions.map((item) => ({ value: item.key, label: item.label }))} onChange={(event) => setDefinitionKey(event.target.value)} />
                <Select label={t('email.ui.language')} value={locale} options={languages.map((value) => ({ value, label: value.toUpperCase() }))} onChange={(event) => setLocale(event.target.value)} />
            </div>
            {!definition.mandatory && <Checkbox label={t('email.ui.enabled')} checked={form.data.enabled} onChange={(checked) => form.setData('enabled', checked)} />}
            {definition.mandatory && <Alert tone="info">{t('email.ui.mandatory')}</Alert>}
            <Input label={t('email.ui.subject')} value={form.data.subject} error={form.errors.subject} onChange={(event) => form.setData('subject', event.target.value)} required />
            <Select label={t('email.ui.layout')} value={form.data.layout_id} options={[{ value: '', label: t('email.ui.default_layout') }, ...layouts.filter((item) => item.locale === locale).map((item) => ({ value: item.id, label: item.name }))]} onChange={(event) => form.setData('layout_id', event.target.value || null)} />
            <Textarea label="HTML" value={form.data.html} error={form.errors.html} onChange={(event) => form.setData('html', event.target.value)} rows={12} />
            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('email.ui.variables')}: {definition.variables.map((variable) => `{{${variable.key}}}`).join(', ')}</div>
            <div style={{ display: 'flex', gap: 10 }}><Button type="submit" loading={form.processing}>{t('email.ui.save')}</Button><Button type="button" variant="outline" onClick={showPreview}>{t('email.ui.preview')}</Button>{template?.overridden && <Button type="button" variant="outline" onClick={() => router.delete(route('panel.email.templates.reset', { definition: definitionKey, locale }), { preserveScroll: true })}>{t('email.ui.reset')}</Button>}</div>
        </form>
    </Card><Modal show={preview !== null} onClose={() => setPreview(null)} title={t('email.ui.preview_title')} maxWidth={760}><h3 style={{ marginTop: 0 }}>{preview?.subject}</h3><div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 16 }} dangerouslySetInnerHTML={{ __html: preview?.html ?? '' }} /></Modal></>;
}

function Layouts({ layouts, languages, t }) {
    const [editing, setEditing] = useState(null);
    const empty = { key: '', locale: languages[0] ?? 'pl', name: '', html: '<div>{{content}}</div>', is_default: false };
    const form = useForm(empty);
    const select = (layout) => { setEditing(layout); form.setData(layout ? { key: layout.key, locale: layout.locale, name: layout.name, html: layout.html, is_default: layout.is_default } : empty); form.clearErrors(); };
    const submit = (event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => select(null) }; editing ? form.put(route('panel.email.layouts.update', editing.id), options) : form.post(route('panel.email.layouts.store'), options); };
    return <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        <Card title={editing ? t('email.ui.edit_layout') : t('email.ui.new_layout')}>
            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 14 }}><Input label={t('email.ui.key')} value={form.data.key} error={form.errors.key} onChange={(event) => form.setData('key', event.target.value)} /><Input label={t('email.ui.name')} value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} /><Select label={t('email.ui.language')} value={form.data.locale} options={languages.map((value) => ({ value, label: value.toUpperCase() }))} onChange={(event) => form.setData('locale', event.target.value)} /></div>
                <Checkbox label={t('email.ui.default_for_language')} checked={form.data.is_default} onChange={(checked) => form.setData('is_default', checked)} />
                <Textarea label="HTML" rows={10} value={form.data.html} error={form.errors.html} onChange={(event) => form.setData('html', event.target.value)} />
                <div style={{ display: 'flex', gap: 10 }}><Button type="submit" loading={form.processing}>{t('email.ui.save')}</Button>{editing && <Button type="button" variant="outline" onClick={() => select(null)}>{t('email.ui.cancel')}</Button>}</div>
            </form>
        </Card>
        <Card title={t('email.ui.layouts_title')}><Table columns={[{ header: t('email.ui.name'), render: (row) => row.name }, { header: t('email.ui.language'), render: (row) => row.locale.toUpperCase() }, { header: t('email.ui.default'), render: (row) => row.is_default ? '✓' : '—' }, { header: t('email.ui.actions'), render: (row) => <div style={{ display: 'flex', gap: 8 }}><Button size="sm" variant="outline" onClick={() => select(row)}>{t('email.ui.edit')}</Button><Button size="sm" variant="ghost" onClick={() => router.delete(route('panel.email.layouts.destroy', row.id), { preserveScroll: true })}>{t('email.ui.delete')}</Button></div> }]} rows={layouts} /></Card>
    </div>;
}

function Queue({ definitions, languages, messages, queueHealth, t }) {
    const test = useForm({ definition_key: definitions[0]?.key ?? '', locale: languages[0] ?? 'pl', recipient: '' });
    const definitionLabels = useMemo(() => Object.fromEntries(definitions.map((item) => [item.key, item.label])), [definitions]);
    const actions = (row) => <div style={{ display: 'flex', gap: 6 }}>
        {['failed', 'delivery_unknown'].includes(row.status) && <Button size="sm" variant="outline" onClick={() => {
            if (row.status !== 'delivery_unknown' || confirm(t('email.ui.retry_unknown_warning'))) router.post(route('panel.email.messages.retry', row.id), {}, { preserveScroll: true });
        }}>{t('email.ui.retry')}</Button>}
        {['queued', 'failed', 'delivery_unknown'].includes(row.status) && <Button size="sm" variant="ghost" onClick={() => router.post(route('panel.email.messages.cancel', row.id), {}, { preserveScroll: true })}>{t('email.ui.cancel_message')}</Button>}
    </div>;
    const delayed = queueHealth?.oldest_queued_at && Date.now() - new Date(queueHealth.oldest_queued_at).getTime() > 5 * 60 * 1000;
    return <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        {delayed && <Alert tone="warning">{t('email.ui.queue_delayed', undefined, { count: queueHealth.queued_count })}</Alert>}
        <Card title={t('email.ui.test_title')}><form onSubmit={(event) => { event.preventDefault(); test.post(route('panel.email.test'), { preserveScroll: true }); }} className="grid grid-cols-1 sm:grid-cols-4" style={{ gap: 12, alignItems: 'end' }}><Select label={t('email.ui.message_type')} value={test.data.definition_key} options={definitions.map((item) => ({ value: item.key, label: item.label }))} onChange={(event) => test.setData('definition_key', event.target.value)} /><Select label={t('email.ui.language')} value={test.data.locale} options={languages.map((value) => ({ value, label: value.toUpperCase() }))} onChange={(event) => test.setData('locale', event.target.value)} /><Input type="email" label={t('email.ui.recipient')} value={test.data.recipient} error={test.errors.recipient} onChange={(event) => test.setData('recipient', event.target.value)} /><Button type="submit" loading={test.processing}>{t('email.ui.send_test')}</Button></form></Card>
        <Card title={t('email.ui.queue_title')} description={t('email.ui.queue_description')}><Table columns={[{ header: t('email.ui.created'), render: (row) => row.created_at ? new Date(row.created_at).toLocaleString() : '—' }, { header: t('email.ui.message_type'), render: (row) => definitionLabels[row.definition_key] ?? row.definition_key }, { header: t('email.ui.recipient'), render: (row) => row.recipient }, { header: t('email.ui.status'), render: (row) => <Badge>{t(`email.ui.statuses.${row.status}`)}</Badge> }, { header: t('email.ui.attempts'), align: 'right', render: (row) => row.attempts }, { header: t('email.ui.actions'), render: actions }]} rows={messages} /></Card>
    </div>;
}

import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Pagination from '@/Components/ui/Pagination';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Home, Plus } from 'lucide-react';

const statusTones = {
    draft: 'neutral',
    published: 'success',
    archived: 'warning',
};

export default function Index({ pages }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
    const statusLabels = {
        draft: t('cms.pages.status.draft'),
        published: t('cms.pages.status.published'),
        archived: t('cms.pages.status.archived'),
    };
    const setHomepage = (page) => {
        router.put(route('panel.cms.pages.homepage.update', page.id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('cms.pages.title')}
                    actions={
                        <Button variant="solid" href={route('panel.cms.pages.create')} icon={<Plus size={15} />}>
                            {t('cms.pages.add')}
                        </Button>
                    }
                />
            }
        >
            <Head title={t('cms.pages.title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                {pages.data.length === 0 ? (
                    <div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-surface)' }}>
                        <EmptyState
                            icon={FileText}
                            title={t('cms.pages.empty_title')}
                            description={t('cms.pages.empty_description')}
                            action={
                                <Button variant="solid" href={route('panel.cms.pages.create')}>
                                    {t('cms.pages.add')}
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <>
                        <Table
                            columns={[
                                {
                                    header: t('cms.pages.column_page'),
                                    render: (p) => (
                                        <Link href={route('panel.cms.pages.edit', p.id)} style={{ color: 'var(--text-link)', fontWeight: 600 }}>
                                            {p.title}
                                        </Link>
                                    ),
                                },
                                { header: t('cms.pages.column_url'), render: (p) => <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>/{p.slug}</span> },
                                { header: t('cms.pages.column_status'), render: (p) => <Badge tone={statusTones[p.status]}>{statusLabels[p.status]}</Badge> },
                                {
                                    header: t('cms.pages.column_homepage'),
                                    render: (p) => (p.is_homepage ? (
                                        <Badge tone="success">
                                            <Home size={12} /> {t('cms.pages.homepage_yes')}
                                        </Badge>
                                    ) : (
                                        <Button variant="ghost" size="sm" onClick={() => setHomepage(p)}>
                                            {t('cms.pages.set_as_homepage')}
                                        </Button>
                                    )),
                                },
                            ]}
                            rows={pages.data}
                        />
                        <Pagination paginator={pages} />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

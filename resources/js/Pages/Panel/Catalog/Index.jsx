import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Pagination from '@/Components/ui/Pagination';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';

const statusTones = {
    draft: 'neutral',
    published: 'success',
    archived: 'warning',
};

export default function Index({ products }) {
    const t = useTranslate();
    const typeLabels = {
        physical: t('catalog.index.type_physical'),
        digital: t('catalog.index.type_digital'),
    };
    const statusLabels = {
        draft: t('catalog.index.status_draft'),
        published: t('catalog.index.status_published'),
        archived: t('catalog.index.status_archived'),
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('catalog.index.title')}
                    actions={
                        <>
                            <Button variant="outline" href={route('panel.catalog.products.references.index')}>
                                {t('catalog.index.brands_categories')}
                            </Button>
                            <Button variant="solid" href={route('panel.catalog.products.create')} icon={<Plus size={15} />}>
                                {t('catalog.index.add_product')}
                            </Button>
                        </>
                    }
                />
            }
        >
            <Head title={t('catalog.index.title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {products.data.length === 0 ? (
                    <div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-surface)' }}>
                        <EmptyState
                            icon={Package}
                            title={t('catalog.index.empty_title')}
                            description={t('catalog.index.empty_description')}
                            action={
                                <Button variant="solid" href={route('panel.catalog.products.create')}>
                                    {t('catalog.index.add_product')}
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <>
                        <Table
                            columns={[
                                {
                                    header: t('catalog.index.column_product'),
                                    render: (p) => (
                                        <Link href={route('panel.catalog.products.edit', p.id)} style={{ color: 'var(--text-link)', fontWeight: 600 }}>
                                            {p.name}
                                        </Link>
                                    ),
                                },
                                { header: t('catalog.index.column_sku'), render: (p) => <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>{p.sku}</span> },
                                { header: t('catalog.index.column_type'), render: (p) => typeLabels[p.type] },
                                { header: t('catalog.index.column_status'), render: (p) => <Badge tone={statusTones[p.status]}>{statusLabels[p.status]}</Badge> },
                            ]}
                            rows={products.data}
                        />
                        <Pagination paginator={products} />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

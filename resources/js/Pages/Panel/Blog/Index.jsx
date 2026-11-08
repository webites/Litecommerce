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
import { BookOpen, FolderTree, Plus } from 'lucide-react';

const tones = { draft: 'neutral', published: 'success', scheduled: 'info', archived: 'warning' };

export default function Index({ posts }) {
    const t = useTranslate();
    const error = usePage().props.flash?.error;
    const labels = {
        draft: t('blog.posts.statuses.draft', 'Draft'),
        published: t('blog.posts.statuses.published', 'Published'),
        scheduled: t('blog.posts.statuses.scheduled', 'Scheduled'),
        archived: t('blog.posts.statuses.archived', 'Archived'),
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('blog.posts.index_title', 'Blog')} actions={<div style={{ display: 'flex', gap: 8 }}><Button variant="outline" href={route('panel.blog.categories.index')} icon={<FolderTree size={15} />}>{t('blog.posts.categories_link', 'Categories')}</Button><Button href={route('panel.blog.posts.create')} icon={<Plus size={15} />}>{t('blog.posts.new_post', 'New post')}</Button></div>} />}>
            <Head title={t('blog.posts.index_title', 'Blog')} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {error && <Alert tone="danger">{error}</Alert>}
                {posts.data.length === 0 ? (
                    <EmptyState icon={BookOpen} title={t('blog.posts.empty_title', 'No posts')} description={t('blog.posts.empty_description', 'Create your first blog post. It can stay a draft, or be published immediately or at a scheduled time.')} action={<Button href={route('panel.blog.posts.create')}>{t('blog.posts.new_post', 'New post')}</Button>} />
                ) : (
                    <>
                        <Table
                            columns={[
                                { header: t('blog.posts.column_post', 'Post'), render: (post) => <Link href={route('panel.blog.posts.edit', post.id)} style={{ color: 'var(--text-link)', fontWeight: 600 }}>{post.title}</Link> },
                                { header: t('blog.posts.column_date', 'Date'), render: (post) => post.date ? new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(post.date)) : '—' },
                                { header: t('blog.posts.column_status', 'Status'), render: (post) => <Badge tone={tones[post.status]}>{labels[post.status]}</Badge> },
                            ]}
                            rows={posts.data}
                        />
                        <Pagination paginator={posts} />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

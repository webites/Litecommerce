import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import PageHeader from '@/Components/ui/PageHeader';
import RedirectSuggestionModal from '@/Components/ui/RedirectSuggestionModal';
import Select from '@/Components/ui/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PostForm from './PostForm';

export default function Edit({ post, languages, categories, statuses }) {
    const t = useTranslate();
    const form = useForm(post);
    const [uploadingFeaturedImage, setUploadingFeaturedImage] = useState(false);
    const status = usePage().props.flash?.status;
    const submit = (event) => { event.preventDefault(); form.put(route('panel.blog.posts.update', post.id)); };
    const archive = () => { if (confirm(t('blog.posts.archive_confirm', 'Archive post ":title"?', { title: post.title }))) router.post(route('panel.blog.posts.archive', post.id)); };
    const changeLocale = (locale) => router.visit(route('panel.blog.posts.edit', { post: post.id, locale }));
    const uploadFeaturedImage = (file) => {
        setUploadingFeaturedImage(true);
        router.post(route('panel.blog.posts.featured-image.store', post.id), { image: file }, {
            preserveScroll: true,
            onFinish: () => setUploadingFeaturedImage(false),
        });
    };
    const removeFeaturedImage = () => router.delete(route('panel.blog.posts.featured-image.destroy', post.id), { preserveScroll: true });

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={post.title}
                    actions={(
                        <>
                            {languages.length > 1 && (
                                <div style={{ width: 160 }}>
                                    <Select
                                        value={post.locale}
                                        options={languages.map((language) => ({ label: language.name, value: language.code }))}
                                        onChange={(event) => changeLocale(event.target.value)}
                                    />
                                </div>
                            )}
                            <Button variant="destructive" onClick={archive}>{t('blog.posts.archive', 'Archive')}</Button>
                        </>
                    )}
                />
            )}
        >
            <Head title={post.title} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {status && <Alert tone="success">{status}</Alert>}
                {post.isUntranslated && <Alert tone="info">{t('blog.posts.untranslated_notice', 'This language does not have its own version yet. The form shows a copy of the base content; saving will create a translation.')}</Alert>}
                <PostForm
                    form={form}
                    languages={languages}
                    categories={categories}
                    statuses={statuses}
                    onSubmit={submit}
                    submitLabel={t('blog.posts.edit_submit', 'Save post')}
                    hideLocaleField
                    featuredImageUrl={post.featured_image_url}
                    onFeaturedImageUpload={uploadFeaturedImage}
                    onFeaturedImageRemove={removeFeaturedImage}
                    uploadingFeaturedImage={uploadingFeaturedImage}
                />
            </div>
            <RedirectSuggestionModal />
        </AuthenticatedLayout>
    );
}

import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import SingleImageUpload from '@/Components/ui/SingleImageUpload';
import Textarea from '@/Components/ui/Textarea';
import WysiwygEditor from '@/Components/ui/WysiwygEditor';
import { useTranslate } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

export default function PostForm({ form, languages, categories, statuses, onSubmit, submitLabel, onLocaleChange, hideLocaleField = false, featuredImageUrl, onFeaturedImageUpload, onFeaturedImageRemove, uploadingFeaturedImage = false }) {
    const t = useTranslate();
    const toggleCategory = (id, checked) => {
        form.setData('category_ids', checked ? [...form.data.category_ids, id] : form.data.category_ids.filter((value) => value !== id));
    };

    return (
        <form onSubmit={onSubmit} style={{ maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
            <Card title={t('blog.posts.form.content_title', 'Post content')} description={t('blog.posts.form.content_description', 'Content is saved separately for the selected language.')}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {hideLocaleField ? (
                        <Input id="title" label={t('blog.posts.form.field_title', 'Title')} required value={form.data.title} error={form.errors.title} onChange={(event) => form.setData('title', event.target.value)} />
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
                            <Select id="locale" label={t('blog.posts.form.field_language', 'Language')} value={form.data.locale} options={languages.map((language) => ({ label: language.name, value: language.code }))} error={form.errors.locale} onChange={(event) => onLocaleChange ? onLocaleChange(event.target.value) : form.setData('locale', event.target.value)} />
                            <Input id="title" label={t('blog.posts.form.field_title', 'Title')} required value={form.data.title} error={form.errors.title} onChange={(event) => form.setData('title', event.target.value)} />
                        </div>
                    )}
                    <Input id="slug" label={t('blog.posts.form.field_slug', 'Slug')} prefix="/blog/" required value={form.data.slug} error={form.errors.slug} onChange={(event) => form.setData('slug', event.target.value)} />
                    <Textarea id="excerpt" label={t('blog.posts.form.field_excerpt', 'Excerpt')} rows={3} value={form.data.excerpt} error={form.errors.excerpt} onChange={(value) => form.setData('excerpt', value)} />
                    <WysiwygEditor label={t('blog.posts.form.field_content', 'Content')} value={form.data.content_html} error={form.errors.content_html} onChange={(value) => form.setData('content_html', value)} />
                </div>
            </Card>

            <Card title={t('blog.posts.form.featured_image_title', 'Featured image')} description={t('blog.posts.form.featured_image_description', 'Shown on the blog listing and on the post page.')}>
                {onFeaturedImageUpload ? (
                    <SingleImageUpload
                        label={featuredImageUrl ? t('blog.posts.form.featured_image_change', 'Change image') : t('blog.posts.form.featured_image_add', 'Add image')}
                        imageUrl={featuredImageUrl}
                        onUpload={onFeaturedImageUpload}
                        onRemove={onFeaturedImageRemove}
                        uploading={uploadingFeaturedImage}
                    />
                ) : (
                    <p style={{ margin: 0, fontSize: 13, color: 'var(--text-secondary)' }}>
                        {t('blog.posts.form.featured_image_available_after_create', 'The image can be added right after the post is created.')}
                    </p>
                )}
            </Card>

            <Card title={t('blog.posts.form.categories_title', 'Categories')} description={t('blog.posts.form.categories_description', 'A post can belong to multiple categories.')}>
                {categories.length === 0 ? <p style={{ margin: 0, fontSize: 13, color: 'var(--text-secondary)' }}>{t('blog.posts.form.categories_empty', 'No categories. You can save the post without a category.')}</p> : (
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                        {categories.map((category) => <Checkbox key={category.id} label={category.name} checked={form.data.category_ids.includes(category.id)} onChange={(checked) => toggleCategory(category.id, checked)} />)}
                    </div>
                )}
                {form.errors.category_ids && <p style={{ margin: '10px 0 0', fontSize: 12, color: 'var(--danger-text)' }}>{form.errors.category_ids}</p>}
            </Card>

            <Card title={t('blog.posts.form.seo_title', 'SEO')} description={t('blog.posts.form.seo_description', 'If left blank, the post title and excerpt will be used.')}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Input id="seo_title" label={t('blog.posts.form.field_seo_title', 'SEO title')} value={form.data.seo_title} error={form.errors.seo_title} onChange={(event) => form.setData('seo_title', event.target.value)} />
                    <Textarea id="seo_description" label={t('blog.posts.form.field_seo_description', 'SEO description')} rows={3} value={form.data.seo_description} error={form.errors.seo_description} onChange={(value) => form.setData('seo_description', value)} />
                </div>
            </Card>

            <Card title={t('blog.posts.form.publish_title', 'Publishing')} description={t('blog.posts.form.publish_description', 'A future date will publish the post automatically on read — no cron dependency.')}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
                        <Select id="status" label={t('blog.posts.form.field_status', 'Status')} value={form.data.status} options={statuses} error={form.errors.status} onChange={(event) => form.setData('status', event.target.value)} />
                        <Input id="publish_at" type="datetime-local" label={t('blog.posts.form.field_publish_at', 'Publish date')} value={form.data.publish_at} error={form.errors.publish_at} onChange={(event) => form.setData('publish_at', event.target.value)} />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        <Button type="submit" loading={form.processing}>{submitLabel}</Button>
                        <Link href={route('panel.blog.posts.index')} style={{ fontSize: 13, color: 'var(--text-secondary)' }}>{t('blog.posts.form.cancel', 'Cancel')}</Link>
                    </div>
                </div>
            </Card>
        </form>
    );
}

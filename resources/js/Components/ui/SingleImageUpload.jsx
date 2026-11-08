import { ImageOff } from 'lucide-react';
import { useRef } from 'react';
import { useTranslate } from '@/lib/i18n';
import Button from './Button';

/**
 * One image tied to an owner via a single foreign key (brand logo,
 * category image) — as opposed to an ordered gallery. Upload/remove are
 * delegated to the caller so it can post to the right Inertia route.
 */
export default function SingleImageUpload({ label, removeLabel, imageUrl, onUpload, onRemove, uploading = false }) {
    const t = useTranslate();
    const inputRef = useRef(null);
    const resolvedLabel = label ?? t('app.single_image_upload_label', 'Image');
    const resolvedRemoveLabel = removeLabel ?? t('app.single_image_upload_remove', 'Remove');

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div
                style={{
                    width: 48, height: 48, flexShrink: 0,
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-subtle)',
                    background: 'var(--gray-2)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    overflow: 'hidden',
                }}
            >
                {imageUrl ? (
                    <img src={imageUrl} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                ) : (
                    <ImageOff size={16} color="var(--text-tertiary)" />
                )}
            </div>
            <Button type="button" variant="outline" size="sm" loading={uploading} onClick={() => inputRef.current?.click()}>
                {resolvedLabel}
            </Button>
            {imageUrl && (
                <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                    {resolvedRemoveLabel}
                </Button>
            )}
            <input
                ref={inputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp,image/gif"
                hidden
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) onUpload(file);
                    event.target.value = '';
                }}
            />
        </div>
    );
}

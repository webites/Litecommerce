import Modal from '@/Components/ui/Modal';
import { useTranslate } from '@/lib/i18n';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

function TagRow({ name, label }) {
    const [copied, setCopied] = useState(false);
    const token = `{{${name}}}`;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(token);
            setCopied(true);
            setTimeout(() => setCopied(false), 1200);
        } catch {
            // Clipboard API unavailable (e.g. insecure context) — the token is still visible to copy by hand.
        }
    };

    return (
        <button
            type="button"
            onClick={copy}
            style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%',
                padding: '8px 10px', borderRadius: 'var(--radius-md, 8px)', border: '1px solid var(--border-subtle)',
                background: 'var(--bg-canvas)', cursor: 'pointer', textAlign: 'left', gap: 12,
            }}
        >
            <span>
                <code style={{ fontFamily: 'var(--font-mono)', fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{token}</code>
                <span style={{ display: 'block', fontSize: 12, color: 'var(--text-secondary)', marginTop: 2 }}>{label}</span>
            </span>
            {copied ? <Check size={15} color="var(--success-solid)" /> : <Copy size={15} color="var(--text-tertiary)" />}
        </button>
    );
}

function TagSection({ title, description, tags }) {
    const t = useTranslate();
    const entries = Object.entries(tags ?? {});

    return (
        <div>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{title}</div>
            <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginBottom: 8 }}>{description}</div>
            {entries.length === 0 ? (
                <div style={{ fontSize: 13, color: 'var(--text-tertiary)', fontStyle: 'italic' }}>{t('cms.dynamic_tags.none_yet')}</div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                    {entries.map(([name, label]) => <TagRow key={name} name={name} label={label} />)}
                </div>
            )}
        </div>
    );
}

/**
 * Reference/copy modal for {{tag}} placeholders any block's text field can use
 * (see Cms\Infrastructure\ThemeBlockRenderer::interpolateTags()). "Ogólne" is
 * Cms\Contracts\GlobalTagRegistry — empty today, but the same extension point
 * a future plugin/theme registers into, so this section stays even when empty.
 */
export default function DynamicTagsModal({ show, onClose, contextTags, generalTags }) {
    const t = useTranslate();

    return (
        <Modal show={show} onClose={onClose} title={t('cms.dynamic_tags.title')} maxWidth={440}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                {contextTags && Object.keys(contextTags).length > 0 && (
                    <TagSection
                        title={t('cms.dynamic_tags.context_title')}
                        description={t('cms.dynamic_tags.context_description')}
                        tags={contextTags}
                    />
                )}
                <TagSection
                    title={t('cms.dynamic_tags.general_title')}
                    description={t('cms.dynamic_tags.general_description')}
                    tags={generalTags}
                />
            </div>
        </Modal>
    );
}

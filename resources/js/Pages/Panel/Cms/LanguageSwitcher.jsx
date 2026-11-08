import { useTranslate } from '@/lib/i18n';
import { ChevronDown } from 'lucide-react';

/**
 * Language switcher for the page builder header — a <select>, not a button
 * group, so it stays usable regardless of how many languages the store has
 * (a segmented control reads fine for 2 languages but not for 5+). A
 * language with no translation row yet is marked in its own option label
 * (a <select>'s options can't carry a separate icon/dot), per the user's
 * own spec ("jasno zakomunikowane").
 */
export default function LanguageSwitcher({ languages, currentLocale, onChange }) {
    const t = useTranslate();
    if (!languages || languages.length < 2) return null;

    return (
        <div style={{ position: 'relative', display: 'inline-flex' }}>
            <select
                value={currentLocale}
                onChange={(e) => onChange(e.target.value)}
                style={{
                    height: 32,
                    border: '1px solid var(--border-default)',
                    borderRadius: 'var(--radius-control)',
                    padding: '0 28px 0 10px',
                    fontSize: 13,
                    fontWeight: 600,
                    fontFamily: 'var(--font-body)',
                    background: 'var(--bg-surface)',
                    color: 'var(--text-primary)',
                    appearance: 'none',
                    cursor: 'pointer',
                }}
            >
                {languages.map((language) => (
                    <option key={language.code} value={language.code}>
                        {language.name}
                        {language.hasTranslation ? '' : ` — ${t('cms.language_switcher.no_translation')}`}
                    </option>
                ))}
            </select>
            <ChevronDown size={14} color="var(--text-tertiary)" style={{ position: 'absolute', right: 8, top: 9, pointerEvents: 'none' }} />
        </div>
    );
}

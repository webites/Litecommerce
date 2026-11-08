import { Code2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { getEditorTools } from '@/lib/editorTools';
import { useTranslate } from '@/lib/i18n';
import './wysiwyg/builtinTools';

export default function WysiwygEditor({ label, value, onChange, error, minHeight = 320 }) {
    const t = useTranslate();
    const editorRef = useRef(null);
    const savedRange = useRef(null);
    const [focused, setFocused] = useState(false);
    const [sourceMode, setSourceMode] = useState(false);
    const [openTool, setOpenTool] = useState(null);
    const tools = getEditorTools();

    useEffect(() => {
        if (!focused && editorRef.current && editorRef.current.innerHTML !== value) {
            editorRef.current.innerHTML = value || '';
        }
    }, [focused, value]);

    useEffect(() => {
        const remember = () => {
            const selection = window.getSelection();
            if (selection?.rangeCount && editorRef.current?.contains(selection.anchorNode)) {
                savedRange.current = selection.getRangeAt(0).cloneRange();
            }
        };
        document.addEventListener('selectionchange', remember);

        return () => document.removeEventListener('selectionchange', remember);
    }, []);

    const exec = (command, commandValue = null) => {
        const editor = editorRef.current;
        if (!editor) return;
        editor.focus();
        if (savedRange.current) {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange.current);
        }
        document.execCommand(command, false, commandValue);
        onChange(editor.innerHTML);
    };

    const api = { exec, insertHtml: (html) => exec('insertHTML', html), t };

    const toggleSource = () => {
        if (sourceMode && editorRef.current) editorRef.current.innerHTML = value || '';
        setSourceMode(!sourceMode);
    };

    const press = (tool) => {
        if (tool.Dialog) setOpenTool(tool.key);
        else tool.run(api);
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            {label && <label style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{label}</label>}
            <div style={{ border: `1px solid ${error ? 'var(--danger-solid)' : focused ? 'var(--brand-solid)' : 'var(--border-default)'}`, borderRadius: 'var(--radius-control)', background: 'var(--bg-surface)', overflow: 'hidden' }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, padding: 8, borderBottom: '1px solid var(--border-subtle)', background: 'var(--bg-subtle)' }}>
                    <select disabled={sourceMode} aria-label={t('app.wysiwyg.format_select', 'Text format')} defaultValue="p" onChange={(event) => exec('formatBlock', event.target.value)} style={{ height: 30, border: '1px solid var(--border-default)', borderRadius: 6, background: 'var(--bg-surface)', color: 'var(--text-primary)', fontSize: 12 }}>
                        <option value="p">{t('app.wysiwyg.format_paragraph', 'Paragraph')}</option>
                        <option value="h2">{t('app.wysiwyg.format_heading_2', 'Heading 2')}</option>
                        <option value="h3">{t('app.wysiwyg.format_heading_3', 'Heading 3')}</option>
                        <option value="h4">{t('app.wysiwyg.format_heading_4', 'Heading 4')}</option>
                    </select>
                    {tools.map((tool) => {
                        const Icon = tool.icon;
                        const toolLabel = t(tool.labelKey, tool.label);

                        return (
                            <button key={tool.key} type="button" disabled={sourceMode} title={toolLabel} aria-label={toolLabel} onMouseDown={(event) => event.preventDefault()} onClick={() => press(tool)} className="lc-wysiwyg-tool"><Icon size={15} /></button>
                        );
                    })}
                    <button type="button" title={t('app.wysiwyg.source', 'HTML source')} aria-label={t('app.wysiwyg.source', 'HTML source')} aria-pressed={sourceMode} onClick={toggleSource} className="lc-wysiwyg-tool" style={{ marginLeft: 'auto' }}><Code2 size={15} /></button>
                </div>
                <div ref={editorRef} className="lc-wysiwyg-content" style={{ minHeight }} contentEditable suppressContentEditableWarning hidden={sourceMode} onFocus={() => setFocused(true)} onBlur={() => setFocused(false)} onInput={(event) => onChange(event.currentTarget.innerHTML)} />
                {sourceMode && <textarea aria-label={t('app.wysiwyg.source', 'HTML source')} className="lc-wysiwyg-source" style={{ minHeight }} spellCheck={false} value={value || ''} onChange={(event) => onChange(event.target.value)} />}
            </div>
            {error && <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>}
            {tools.filter((tool) => tool.Dialog).map(({ key, Dialog }) => (
                <Dialog key={key} open={openTool === key} onClose={() => setOpenTool(null)} api={api} />
            ))}
        </div>
    );
}

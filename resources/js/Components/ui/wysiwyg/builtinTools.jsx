import { Bold, Code, Image as ImageIcon, Italic, Link as LinkIcon, List, ListOrdered, Minus, Quote, Redo2, Strikethrough, Table as TableIcon, Underline, Undo2 } from 'lucide-react';
import { registerEditorTool } from '@/lib/editorTools';
import MediaLibraryDialog, { imageHtml } from './MediaLibraryDialog';

const commandTools = [
    { key: 'undo', icon: Undo2, label: 'Undo', command: 'undo' },
    { key: 'redo', icon: Redo2, label: 'Redo', command: 'redo' },
    { key: 'bold', icon: Bold, label: 'Bold', command: 'bold' },
    { key: 'italic', icon: Italic, label: 'Italic', command: 'italic' },
    { key: 'underline', icon: Underline, label: 'Underline', command: 'underline' },
    { key: 'strikethrough', icon: Strikethrough, label: 'Strikethrough', command: 'strikeThrough' },
    { key: 'unordered_list', icon: List, label: 'Bulleted list', command: 'insertUnorderedList' },
    { key: 'ordered_list', icon: ListOrdered, label: 'Numbered list', command: 'insertOrderedList' },
    { key: 'quote', icon: Quote, label: 'Quote', command: 'formatBlock', value: 'blockquote' },
    { key: 'code', icon: Code, label: 'Code', command: 'formatBlock', value: 'pre' },
    { key: 'horizontal_rule', icon: Minus, label: 'Horizontal rule', command: 'insertHorizontalRule' },
];

commandTools.forEach(({ key, icon, label, command, value }, index) => registerEditorTool({
    key, icon, label, labelKey: `app.wysiwyg.tool_${key}`, order: (index + 1) * 10, run: (api) => api.exec(command, value),
}));

registerEditorTool({
    key: 'link',
    icon: LinkIcon,
    label: 'Add link',
    labelKey: 'app.wysiwyg.add_link',
    order: 200,
    run: (api) => {
        const url = window.prompt(api.t('app.wysiwyg.link_prompt', 'Link URL'));
        if (url) api.exec('createLink', url);
    },
});

registerEditorTool({
    key: 'image',
    icon: ImageIcon,
    label: 'Insert image from the media library',
    labelKey: 'app.wysiwyg.add_image',
    order: 210,
    Dialog: ({ open, onClose, api }) => (
        <MediaLibraryDialog open={open} onClose={onClose} onSelect={(media, alt) => api.insertHtml(imageHtml(media, alt))} />
    ),
});

registerEditorTool({
    key: 'table',
    icon: TableIcon,
    label: 'Insert table',
    labelKey: 'app.wysiwyg.add_table',
    order: 220,
    run: (api) => api.insertHtml('<table><tbody><tr><th>&nbsp;</th><th>&nbsp;</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><p><br></p>'),
});

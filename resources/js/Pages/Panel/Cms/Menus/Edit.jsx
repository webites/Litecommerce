import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import SortableTree from '@/Components/ui/SortableTree';
import Tabs from '@/Components/ui/Tabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';

/** Nested {children:[...]} tree (as stored) -> flat list with a UI-only `depth` field, for SortableTree. */
function flattenTree(nodes, depth = 0) {
    let result = [];
    nodes.forEach((node) => {
        const { children, ...rest } = node;
        result.push({ ...rest, depth });
        if (children?.length) result = result.concat(flattenTree(children, depth + 1));
    });
    return result;
}

/** Flat list with `depth` -> the nested {children:[...]} shape SaveMenuItems expects. `autoLabel` is a display-only hint (see MenuController::annotateAutoLabels) and never sent to the server. */
function unflattenTree(flat) {
    const root = { children: [] };
    const stack = [root];
    flat.forEach((item) => {
        const { depth, autoLabel, ...rest } = item;
        while (stack.length > depth + 1) stack.pop();
        const node = { ...rest, children: [] };
        stack[stack.length - 1].children.push(node);
        stack.push(node);
    });
    return root.children;
}

export default function Edit({ menu, locations, pages, archetypes, widgets }) {
    const t = useTranslate();
    const typeLabels = {
        page: t('cms.menus.editor.type_page', 'Page'),
        archetype: t('cms.menus.editor.type_archetype', 'Archetype'),
        widget: t('cms.menus.editor.type_widget', 'Widget'),
        custom: t('cms.menus.editor.type_custom', 'Link'),
    };
    const [items, setItems] = useState(() => flattenTree(menu.content));
    const [activeTab, setActiveTab] = useState('pages');
    const [customLabel, setCustomLabel] = useState('');
    const [customUrl, setCustomUrl] = useState('');
    const [savingItems, setSavingItems] = useState(false);

    const settingsForm = useForm({ name: menu.name, locationId: menu.locationId ?? '' });

    const saveSettings = (event) => {
        event.preventDefault();
        settingsForm.put(route('panel.cms.menus.update', menu.id), { preserveScroll: true });
    };

    const addItem = (node) => {
        setItems((current) => [...current, { id: crypto.randomUUID(), depth: 0, targetBlank: false, labelOverride: null, ...node }]);
    };

    const updateItem = (id, changes) => {
        setItems((current) => current.map((item) => (item.id === id ? { ...item, ...changes } : item)));
    };

    const removeItem = (id) => {
        // Removing a parent also removes its own descendants — anything with a
        // greater depth immediately following it in the flat list.
        setItems((current) => {
            const index = current.findIndex((item) => item.id === id);
            if (index === -1) return current;
            const depth = current[index].depth;
            let end = index + 1;
            while (end < current.length && current[end].depth > depth) end += 1;
            return [...current.slice(0, index), ...current.slice(end)];
        });
    };

    const saveItems = () => {
        setSavingItems(true);
        router.put(route('panel.cms.menus.items.update', menu.id), { tree: unflattenTree(items) }, {
            preserveScroll: true,
            onFinish: () => setSavingItems(false),
        });
    };

    const addCustomLink = () => {
        if (customUrl.trim() === '') return;
        addItem({ type: 'custom', url: customUrl.trim(), labelOverride: customLabel.trim() || null, referenceKey: null, referenceId: null, autoLabel: customUrl.trim() });
        setCustomLabel('');
        setCustomUrl('');
    };

    const sourceTabs = [
        { key: 'pages', label: t('cms.menus.editor.tab_pages', 'Pages') },
        ...archetypes.map((archetype) => ({ key: `archetype:${archetype.key}`, label: archetype.label })),
        { key: 'custom', label: t('cms.menus.editor.tab_custom', 'Custom link') },
        { key: 'widgets', label: t('cms.menus.editor.tab_widgets', 'Widgets') },
    ];

    return (
        <AuthenticatedLayout header={<PageHeader title={menu.name} description={t('cms.menus.editor.header_description', 'Menu editor')} />}>
            <Head title={menu.name} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                <Card title={t('cms.menus.editor.settings_card', 'Menu settings')}>
                    <form onSubmit={saveSettings} style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 480 }}>
                        <Input
                            label={t('cms.menus.editor.name_label', 'Menu name')}
                            value={settingsForm.data.name}
                            onChange={(e) => settingsForm.setData('name', e.target.value)}
                            error={settingsForm.errors.name}
                        />
                        <Select
                            label={t('cms.menus.editor.location_label', 'Theme location')}
                            value={settingsForm.data.locationId}
                            onChange={(e) => settingsForm.setData('locationId', e.target.value)}
                            options={[{ value: '', label: t('cms.menus.editor.location_unassigned_option', '— unassigned —') }, ...locations.map((l) => ({ value: l.id, label: l.label }))]}
                            helpText={t('cms.menus.editor.location_help', "The theme decides which locations exist — see its manifest.")}
                        />
                        <div><Button type="submit" variant="solid" loading={settingsForm.processing}>{t('cms.menus.editor.save_settings', 'Save settings')}</Button></div>
                    </form>
                </Card>

                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 20 }}>
                    <Card title={t('cms.menus.editor.add_items_card', 'Add items')}>
                        <Tabs
                            variant="pill"
                            ariaLabel={t('cms.menus.editor.add_items_card', 'Add items')}
                            items={sourceTabs}
                            value={activeTab}
                            onChange={setActiveTab}
                        />

                        {activeTab === 'pages' && (
                            <PickerList
                                entries={pages}
                                onAdd={(entry) => addItem({ type: 'page', referenceId: String(entry.id), referenceKey: null, url: null, labelOverride: null, autoLabel: entry.label })}
                                t={t}
                            />
                        )}

                        {archetypes.map((archetype) => activeTab === `archetype:${archetype.key}` && (
                            <PickerList
                                key={archetype.key}
                                entries={archetype.instances}
                                onAdd={(entry) => addItem({ type: 'archetype', referenceKey: archetype.key, referenceId: String(entry.id), url: null, labelOverride: null, autoLabel: entry.label })}
                                t={t}
                            />
                        ))}

                        {activeTab === 'custom' && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                <Input
                                    label={t('cms.menus.editor.custom_link_text_label', 'Link text')}
                                    value={customLabel}
                                    onChange={(e) => setCustomLabel(e.target.value)}
                                    placeholder={t('cms.menus.editor.custom_link_text_placeholder', 'e.g. Terms of service')}
                                />
                                <Input
                                    label={t('cms.menus.editor.custom_link_url_label', 'URL address')}
                                    value={customUrl}
                                    onChange={(e) => setCustomUrl(e.target.value)}
                                    placeholder={t('cms.menus.editor.custom_link_url_placeholder', 'https://...')}
                                />
                                <div><Button variant="outline" size="sm" icon={<Plus size={14} />} onClick={addCustomLink}>{t('cms.menus.editor.add_custom_link', 'Add to menu')}</Button></div>
                            </div>
                        )}

                        {activeTab === 'widgets' && (
                            <PickerList
                                entries={widgets.map((w) => ({ id: w.key, label: w.label }))}
                                onAdd={(entry) => addItem({ type: 'widget', referenceKey: entry.id, referenceId: null, url: null, labelOverride: null, autoLabel: entry.label })}
                                t={t}
                            />
                        )}
                    </Card>

                    <Card
                        title={t('cms.menus.editor.structure_card', 'Menu structure')}
                        description={t('cms.menus.editor.structure_description', 'Drag to reorder, or use the arrows.')}
                    >
                        {items.length === 0 ? (
                            <p style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>{t('cms.menus.editor.empty_structure', 'This menu is still empty — add the first item from the left.')}</p>
                        ) : (
                            <SortableTree
                                items={items}
                                onChange={setItems}
                                labels={{
                                    outdent: t('cms.menus.editor.tree_outdent', 'Decrease indent'),
                                    indent: t('cms.menus.editor.tree_indent', 'Nest under item above'),
                                    moveUp: t('cms.menus.editor.tree_move_up', 'Move up'),
                                    moveDown: t('cms.menus.editor.tree_move_down', 'Move down'),
                                }}
                                renderItem={(item) => (
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                        <span style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-tertiary)', width: 64, flexShrink: 0 }}>
                                            {typeLabels[item.type] ?? item.type}
                                        </span>
                                        <Input
                                            value={item.labelOverride ?? ''}
                                            onChange={(e) => updateItem(item.id, { labelOverride: e.target.value === '' ? null : e.target.value })}
                                            placeholder={item.autoLabel ?? t('cms.menus.editor.item_label_placeholder_fallback', '(not found — remove this item)')}
                                            style={{ flex: 1, minWidth: 160 }}
                                        />
                                        {item.type !== 'widget' && (
                                            <Checkbox
                                                label={t('cms.menus.editor.new_tab_checkbox', 'New tab')}
                                                checked={!!item.targetBlank}
                                                onChange={(checked) => updateItem(item.id, { targetBlank: checked })}
                                            />
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => removeItem(item.id)}
                                            aria-label={t('cms.menus.editor.remove_item_aria', 'Remove item')}
                                            style={{ display: 'flex', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-tertiary)' }}
                                        >
                                            <X size={16} />
                                        </button>
                                    </div>
                                )}
                            />
                        )}

                        <div style={{ marginTop: 16 }}>
                            <Button variant="solid" loading={savingItems} onClick={saveItems}>{t('cms.menus.editor.save_items', 'Save menu')}</Button>
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function PickerList({ entries, onAdd, t }) {
    if (entries.length === 0) {
        return <p style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>{t('cms.menus.editor.no_entries', 'No entries to choose from.')}</p>;
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4, maxHeight: 320, overflowY: 'auto' }}>
            {entries.map((entry) => (
                <div key={entry.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8, padding: '6px 4px' }}>
                    <span style={{ fontSize: 13, color: 'var(--text-primary)' }}>{entry.label}</span>
                    <Button variant="ghost" size="sm" icon={<Plus size={14} />} onClick={() => onAdd(entry)}>{t('cms.menus.editor.add_entry', 'Add')}</Button>
                </div>
            ))}
        </div>
    );
}

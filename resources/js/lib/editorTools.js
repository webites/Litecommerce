const tools = new Map();

// A tool is { key, icon, labelKey, label, order?, run?(api), Dialog?({ open, onClose, api }) }; registering an existing key replaces it.
export function registerEditorTool(tool) {
    tools.set(tool.key, { order: 1000, ...tool });
}

export function getEditorTools() {
    return [...tools.values()].sort((a, b) => a.order - b.order);
}

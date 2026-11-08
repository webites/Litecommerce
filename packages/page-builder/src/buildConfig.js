import { createSearchPickerField } from './fields/searchPickerField.jsx';
import { createImagePickerField } from './fields/imagePickerField.jsx';
import { createGenericBlockRenderer } from './renderers/genericBlock.jsx';
import { ContainerBlockRenderer } from './renderers/containerBlock.jsx';
import { withBlockWrapper, SfMainRoot } from './renderers/blockWrapper.jsx';

export function buildPuckConfig(blocks, rootFields = {}, endpoints = {}) {
    const components = {};
    const categories = {};

    for (const [type, definition] of Object.entries(blocks)) {
        components[type] = {
            label: definition.label ?? type,
            fields: mapFields(definition.fields ?? {}, endpoints),
            defaultProps: definition.default_props ?? {},
            render: withBlockWrapper(type === 'container' ? ContainerBlockRenderer : createGenericBlockRenderer(type)),
        };

        const category = definition.category ?? 'blocks';
        categories[category] = categories[category] ?? { components: [] };
        categories[category].components.push(type);
    }

    // Real theme.css (loaded into the canvas iframe, see PageBuilderEditor.jsx)
    // styles .sf-main's width/padding — wrapping the canvas in it here mirrors
    // theme::layout's own structure so block width/line-length previews match.
    const root = { fields: mapFields(rootFields, endpoints), render: SfMainRoot };

    return { categories, components, root };
}

function mapFields(fields, endpoints) {
    const mapped = {};

    for (const [prop, field] of Object.entries(fields)) {
        mapped[prop] = mapField(field, endpoints);
    }

    return mapped;
}

function mapField(field, endpoints) {
    switch (field.type) {
        case 'product-picker':
            return {
                label: field.label,
                ...createSearchPickerField({
                    searchUrl: endpoints.productSearchUrl,
                    multiple: Boolean(field.multiple),
                    placeholder: field.placeholder ?? 'Szukaj produktu…',
                }),
            };
        case 'image-picker':
            return {
                label: field.label,
                ...createImagePickerField({ uploadUrl: endpoints.imageUploadUrl }),
            };
        case 'array':
            return {
                type: 'array',
                label: field.label,
                arrayFields: mapFields(field.fields ?? {}, endpoints),
                defaultItemProps: field.default_item_props ?? {},
            };
        case 'object':
            return {
                type: 'object',
                label: field.label,
                objectFields: mapFields(field.fields ?? {}, endpoints),
            };
        default:
            return field;
    }
}

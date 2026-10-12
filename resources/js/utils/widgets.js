import { defineAsyncComponent } from 'vue';

/**
 * Turns the widget files found by import.meta.glob into async components keyed by file name, so a module's
 * Resources/js/Widgets/LowStock.vue is the widget named "LowStock".
 */
export function indexWidgets(glob) {
    const widgets = {};

    for (const [path, loader] of Object.entries(glob)) {
        widgets[path.replace(/^.*\/Widgets\//, '').replace(/\.vue$/, '')] = defineAsyncComponent(loader);
    }

    return widgets;
}

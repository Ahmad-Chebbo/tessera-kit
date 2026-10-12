import { describe, expect, it } from 'vitest';
import { indexWidgets } from '../widgets';

describe('indexWidgets', () => {
    it('names widgets by their file name, wherever the module keeps them', () => {
        const widgets = indexWidgets({
            '/app/Modules/Inventory/Resources/js/Widgets/LowStock.vue': () => Promise.resolve({ render: () => null }),
            '/app/Modules/Expenses/Resources/js/Widgets/Spending.vue': () => Promise.resolve({ render: () => null }),
        });

        expect(Object.keys(widgets)).toEqual(['LowStock', 'Spending']);
    });
});

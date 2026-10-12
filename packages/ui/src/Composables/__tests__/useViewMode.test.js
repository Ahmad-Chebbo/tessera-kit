import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useViewMode } from '../useViewMode.js';

describe('useViewMode', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('defaults to the table view', () => {
        expect(useViewMode('products').mode.value).toBe('table');
    });

    it('honours a custom default', () => {
        expect(useViewMode('products', 'grid').mode.value).toBe('grid');
    });

    it('restores the stored choice per page key', () => {
        localStorage.setItem('view:products', 'grid');

        expect(useViewMode('products').mode.value).toBe('grid');
        expect(useViewMode('orders').mode.value).toBe('table');
    });

    it('ignores a corrupt stored value', () => {
        localStorage.setItem('view:products', 'banana');

        expect(useViewMode('products').mode.value).toBe('table');
    });

    it('persists a change', () => {
        const { mode, setMode } = useViewMode('products');

        setMode('grid');

        expect(mode.value).toBe('grid');
        expect(localStorage.getItem('view:products')).toBe('grid');
    });

    it('does not throw when storage is blocked', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        const { mode, setMode } = useViewMode('products');
        expect(mode.value).toBe('table');

        expect(() => setMode('grid')).not.toThrow();
        expect(mode.value).toBe('grid');
    });
});

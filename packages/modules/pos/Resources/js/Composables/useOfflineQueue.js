import { ref, computed, onMounted, onUnmounted } from 'vue';
import localforage from 'localforage';
import axios from 'axios';
import { useToast } from '@tessera/tessera-ui/Composables/useToast';
import i18n from '@/i18n';

const OFFLINE_QUEUE_KEY = 'pos_offline_sales';
const PRODUCTS_CACHE_KEY = 'pos_products_cache';

const isOnline = ref(navigator.onLine);
const pendingSales = ref([]);
const isSyncing = ref(false);
const lastSyncError = ref(null);

// Initialize localforage
localforage.config({
    name: 'Tessera',
    storeName: 'pos_data',
});

export function useOfflineQueue() {
    const loadPendingSales = async () => {
        try {
            const sales = await localforage.getItem(OFFLINE_QUEUE_KEY);
            pendingSales.value = sales || [];
        } catch (e) {
            console.error('Failed to load offline sales:', e);
            pendingSales.value = [];
        }
    };

    const savePendingSales = async () => {
        try {
            await localforage.setItem(OFFLINE_QUEUE_KEY, pendingSales.value);
        } catch (e) {
            console.error('Failed to save offline sales:', e);
        }
    };

    const queueSale = async (saleData) => {
        const offlineSale = {
            ...saleData,
            offline_id: `offline_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            queued_at: new Date().toISOString(),
        };

        pendingSales.value.push(offlineSale);
        await savePendingSales();

        return offlineSale;
    };

    const removeSale = async (offlineId) => {
        pendingSales.value = pendingSales.value.filter(sale => sale.offline_id !== offlineId);
        await savePendingSales();
    };

    const syncSales = async () => {
        if (!isOnline.value || isSyncing.value || pendingSales.value.length === 0) {
            return { synced: 0, failed: 0 };
        }

        isSyncing.value = true;
        lastSyncError.value = null;

        try {
            const response = await axios.post(route('pos.sync'), {
                sales: pendingSales.value,
            });

            const results = response.data.results;

            // Remove successfully synced sales
            const syncedIds = results
                .filter(r => r.success)
                .map(r => r.offline_id);

            pendingSales.value = pendingSales.value.filter(
                sale => !syncedIds.includes(sale.offline_id)
            );

            await savePendingSales();

            const toast = useToast();
            const { t } = i18n.global;
            if (response.data.synced > 0) {
                toast.success(t('toast.salesSynced', { count: response.data.synced }));
            }
            if (response.data.failed > 0) {
                toast.error(t('toast.syncFailed', { count: response.data.failed }));
            }

            return {
                synced: response.data.synced,
                failed: response.data.failed,
                results: results,
            };
        } catch (e) {
            lastSyncError.value = e.message;
            console.error('Sync failed:', e);
            return { synced: 0, failed: pendingSales.value.length, error: e.message };
        } finally {
            isSyncing.value = false;
        }
    };

    // Products cache for offline browsing
    const cacheProducts = async (products) => {
        try {
            // Convert to plain JSON objects to avoid DataCloneError
            // This removes any Vue reactivity or non-serializable properties
            const plainProducts = JSON.parse(JSON.stringify(products));
            
            await localforage.setItem(PRODUCTS_CACHE_KEY, {
                products: plainProducts,
                cached_at: new Date().toISOString(),
            });
        } catch (e) {
            console.error('Failed to cache products:', e);
        }
    };

    const getCachedProducts = async () => {
        try {
            const cache = await localforage.getItem(PRODUCTS_CACHE_KEY);
            return cache?.products || [];
        } catch (e) {
            console.error('Failed to get cached products:', e);
            return [];
        }
    };

    const pendingCount = computed(() => pendingSales.value.length);

    const handleOnline = () => {
        isOnline.value = true;
        // Auto-sync when back online
        syncSales();
    };

    const handleOffline = () => {
        isOnline.value = false;
    };

    onMounted(() => {
        loadPendingSales();
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return {
        isOnline,
        isSyncing,
        pendingSales,
        pendingCount,
        lastSyncError,
        queueSale,
        removeSale,
        syncSales,
        cacheProducts,
        getCachedProducts,
        loadPendingSales,
    };
}

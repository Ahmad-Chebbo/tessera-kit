<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import { ArrowRight, LogIn, LogOut, Minus, Package, Plus, X } from 'lucide-vue-next';
import Select from '@tessera/tessera-ui/Components/Select.vue';

const props = defineProps({
    products: Array,
    locations: Array,
});

const { t } = useI18n();

const form = useForm({
    from_location_id: '',
    to_location_id: '',
    notes: '',
    items: [],
});

const newItem = ref({
    product_id: '',
    quantity: 1,
    unit_id: '',
});

const selectedProduct = computed(() => {
    return props.products.find(p => p.id === parseInt(newItem.value.product_id));
});

const availableUnits = computed(() => {
    if (!selectedProduct.value) return [];
    return selectedProduct.value.product_units || [];
});

const addItem = () => {
    if (!newItem.value.product_id || !newItem.value.quantity) return;

    const unitId = newItem.value.unit_id ? parseInt(newItem.value.unit_id) : null;
    const existingIndex = form.items.findIndex(
        item => item.product_id === parseInt(newItem.value.product_id) && item.unit_id === unitId
    );

    if (existingIndex >= 0) {
        form.items[existingIndex].quantity += parseFloat(newItem.value.quantity);
    } else {
        const product = props.products.find(p => p.id === parseInt(newItem.value.product_id));
        const unit = unitId ? availableUnits.value.find(u => u.unit_id === unitId) : null;

        form.items.push({
            product_id: parseInt(newItem.value.product_id),
            product_name: product.name,
            product_sku: product.sku,
            quantity: parseFloat(newItem.value.quantity),
            unit_id: unitId,
            unit_name: unit?.unit?.name || null,
            unit_abbreviation: unit?.unit?.abbreviation || null,
        });
    }

    newItem.value = { product_id: '', quantity: 1, unit_id: '' };
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const updateQuantity = (index, qty) => {
    if (qty < 0.0001) {
        removeItem(index);
    } else {
        form.items[index].quantity = qty;
    }
};

const submit = () => {
    form.post(route('inventory.storeTransfer'));
};

const fromLocation = computed(() => {
    return props.locations.find(l => l.id === parseInt(form.from_location_id));
});

const toLocation = computed(() => {
    return props.locations.find(l => l.id === parseInt(form.to_location_id));
});
</script>

<template>
    <Head :title="t('transfers.stockTransfer')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('transfers.stockTransfer') }}</h1>
                    <p class="text-sm text-muted">{{ t('transfers.moveProducts') }}</p>
                </div>
            </div>
        </template>

        <form @submit.prevent="submit" class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column - Transfer Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Locations -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('transfers.transferDetails') }}</h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="from_location_id" :value="t('transfers.fromLocation')" />
                            <Select
                                id="from_location_id"
                                v-model="form.from_location_id"
                                class="mt-1 w-full"
                                required>
                                <option value="">{{ t('transfers.selectSource') }}</option>
                                <option v-for="location in locations" :key="location.id" :value="location.id">
                                    {{ location.name }} ({{ location.type }})
                                </option>
                            </Select>
                            <InputError :message="form.errors.from_location_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="to_location_id" :value="t('transfers.toLocation')" />
                            <Select
                                id="to_location_id"
                                v-model="form.to_location_id"
                                class="mt-1 w-full"
                                required>
                                <option value="">Select destination...</option>
                                <option
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="location.id"
                                    :disabled="location.id === parseInt(form.from_location_id)"
                                >
                                    {{ location.name }} ({{ location.type }})
                                </option>
                            </Select>
                            <InputError :message="form.errors.to_location_id" class="mt-2" />
                        </div>
                    </div>

                    <!-- Visual Transfer -->
                    <div v-if="fromLocation && toLocation" class="mt-6 flex items-center justify-center gap-4 rounded-lg bg-hover-wash p-4">
                        <div class="text-center">
                            <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-status-danger-wash">
                                <LogOut class="h-6 w-6 text-status-danger" />
                            </div>
                            <p class="mt-2 text-sm font-medium text-fg">{{ fromLocation.name }}</p>
                        </div>
                        <ArrowRight class="h-8 w-8 text-faint rtl:rotate-180" />
                        <div class="text-center">
                            <div class="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-status-success-wash">
                                <LogIn class="h-6 w-6 text-status-success" />
                            </div>
                            <p class="mt-2 text-sm font-medium text-fg">{{ toLocation.name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Add Products -->
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">Add Products</h3>

                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <Select
                                v-model="newItem.product_id"
                                @change="newItem.unit_id = ''"
                                class="w-full">
                                <option value="">Select product...</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }}
                                </option>
                            </Select>
                        </div>
                        <div v-if="availableUnits.length > 0" class="w-32">
                            <Select
                                v-model="newItem.unit_id"
                                class="w-full">
                                <option value="">{{ t('units.baseUnit') }}</option>
                                <option v-for="pu in availableUnits" :key="pu.unit_id" :value="pu.unit_id">
                                    {{ pu.unit?.name }} ({{ pu.unit?.abbreviation }})
                                </option>
                            </Select>
                        </div>
                        <div class="w-32">
                            <TextInput
                                v-model="newItem.quantity"
                                type="number"
                                min="0.0001"
                                step="any"
                                class="block w-full"
                                placeholder="Qty"
                            />
                        </div>
                        <button
                            type="button"
                            @click="addItem"
                            :disabled="!newItem.product_id"
                            class="rounded-lg bg-gold px-4 py-2 text-ink hover:bg-gold-deep disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Add
                        </button>
                    </div>

                    <InputError :message="form.errors.items" class="mt-2" />

                    <!-- Items List -->
                    <div v-if="form.items.length > 0" class="mt-6 space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="`${item.product_id}-${item.unit_id}`"
                            class="flex items-center justify-between rounded-lg border border-line p-4"
                        >
                            <div>
                                <p class="font-medium text-fg">
                                    {{ item.product_name }}
                                    <span v-if="item.unit_abbreviation" class="text-xs text-accent">
                                        ({{ item.unit_abbreviation }})
                                    </span>
                                </p>
                                <p v-if="item.product_sku" class="text-sm text-muted">
                                    SKU: {{ item.product_sku }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="updateQuantity(index, item.quantity - 1)"
                                        class="rounded-lg bg-hover-wash p-1 hover:bg-line"
                                    >
                                        <Minus class="h-5 w-5" />
                                    </button>
                                    <input
                                        type="number"
                                        :value="item.quantity"
                                        @change="updateQuantity(index, parseFloat($event.target.value) || 0)"
                                        min="0.0001"
                                        step="any"
                                        class="w-16 rounded border-line py-1 text-center text-sm font-medium text-fg"
                                    />
                                    <button
                                        type="button"
                                        @click="updateQuantity(index, item.quantity + 1)"
                                        class="rounded-lg bg-hover-wash p-1 hover:bg-line"
                                    >
                                        <Plus class="h-5 w-5" />
                                    </button>
                                    <span v-if="item.unit_abbreviation" class="text-xs text-muted">
                                        {{ item.unit_abbreviation }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="removeItem(index)"
                                    class="text-status-danger hover:underline"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-6 rounded-lg border-2 border-dashed border-line p-8 text-center">
                        <Package class="mx-auto h-12 w-12 text-faint" />
                        <p class="mt-2 text-muted">No products added yet</p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Summary -->
            <div class="space-y-6">
                <div class="sticky top-24 rounded-2xl border border-line bg-surface p-6">
                    <h3 class="font-display mb-4 text-lg font-normal text-fg">Transfer Summary</h3>

                    <div class="space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Total Products</span>
                            <span class="font-medium text-fg">{{ form.items.length }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Total Units</span>
                            <span class="font-medium text-fg">
                                {{ form.items.reduce((sum, item) => sum + item.quantity, 0) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <InputLabel for="notes" :value="t('common.notes')" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                            placeholder="Optional notes..."
                        ></textarea>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <Link
                            :href="route('inventory.index')"
                            class="flex-1 rounded-lg bg-hover-wash px-4 py-2.5 text-center text-sm font-medium text-muted hover:bg-line"
                        >
                            {{ t('common.cancel') }}
                        </Link>
                        <PrimaryButton
                            :disabled="form.processing || form.items.length === 0"
                            class="flex-1 justify-center"
                        >
                            Transfer
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </form>
    </SidebarLayout>
</template>

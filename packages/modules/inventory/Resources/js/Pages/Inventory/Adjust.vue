<script setup>
import { computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { ArrowDown, ArrowUp } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';

const props = defineProps({
    products: Array,
    locations: Array,
});

const { t } = useI18n();

const form = useForm({
    product_id: '',
    location_id: '',
    quantity: '',
    unit_id: '',
    notes: '',
});

const submit = () => {
    form.post(route('inventory.storeAdjustment'));
};

const selectedProduct = computed(() => {
    return props.products.find(p => p.id === parseInt(form.product_id));
});

const availableUnits = computed(() => {
    if (!selectedProduct.value) return [];
    return selectedProduct.value.product_units || [];
});

const selectedUnit = computed(() => {
    if (!form.unit_id) return null;
    return availableUnits.value.find(pu => pu.unit_id === parseInt(form.unit_id));
});

const adjustmentType = computed(() => {
    const qty = parseFloat(form.quantity);
    if (!qty || qty === 0) return null;
    return qty > 0 ? 'increase' : 'decrease';
});
</script>

<template>
    <Head :title="t('inventory.adjustment')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('inventory.adjustment') }}
                </h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 border border-line">
            <div class="grid grid-cols-1 gap-6">
                <!-- Product -->
                <div>
                    <InputLabel for="product_id" :value="t('products.productName')" />
                    <Select id="product_id" v-model="form.product_id"
                        class="mt-1 w-full"
                        required>
                        <option value="">{{ t('pos.selectCategory') }}...</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.product_id" class="mt-2" />
                </div>

                <!-- Location -->
                <div>
                    <InputLabel for="location_id" :value="t('inventory.location')" />
                    <Select id="location_id" v-model="form.location_id"
                        class="mt-1 w-full"
                        required>
                        <option value="">{{ t('inventory.location') }}...</option>
                        <option v-for="location in locations" :key="location.id" :value="location.id">
                            {{ location.name }} ({{ location.type }})
                        </option>
                    </Select>
                    <InputError :message="form.errors.location_id" class="mt-2" />
                </div>

                <!-- Unit -->
                <div v-if="availableUnits.length > 0">
                    <InputLabel for="unit_id" :value="t('units.title')" />
                    <Select
                        id="unit_id"
                        v-model="form.unit_id"
                        class="mt-1 w-full">
                        <option value="">{{ t('units.baseUnit') }}</option>
                        <option v-for="pu in availableUnits" :key="pu.unit_id" :value="pu.unit_id">
                            {{ pu.unit?.name }} ({{ pu.unit?.abbreviation }})
                        </option>
                    </Select>
                    <InputError :message="form.errors.unit_id" class="mt-2" />
                </div>

                <!-- Quantity -->
                <div>
                    <InputLabel for="quantity" :value="t('common.quantity')" />
                    <div class="mt-1 flex items-center gap-4">
                        <TextInput id="quantity" v-model="form.quantity" type="number" step="any" class="block w-full"
                            placeholder="Enter positive to add, negative to subtract" required />
                    </div>
                    <p class="mt-1 text-sm text-muted">
                        Use positive numbers to increase stock, negative to decrease
                    </p>
                    <InputError :message="form.errors.quantity" class="mt-2" />
                </div>

                <!-- Adjustment Preview -->
                <div v-if="adjustmentType && selectedProduct" class="rounded-lg border p-4"
                    :class="adjustmentType === 'increase' ? 'border-status-success-border bg-status-success-wash' : 'border-status-danger-border bg-status-danger-wash'">
                    <div class="flex items-center gap-3">
                        <div
                            :class="adjustmentType === 'increase' ? 'text-status-success' : 'text-status-danger'">
                            <ArrowUp v-if="adjustmentType === 'increase'" class="h-8 w-8" />
                            <ArrowDown v-else class="h-8 w-8" />
                        </div>
                        <div>
                            <p class="font-medium"
                                :class="adjustmentType === 'increase' ? 'text-status-success' : 'text-status-danger'">
                                {{ adjustmentType === 'increase' ? 'Stock Increase' : 'Stock Decrease' }}
                            </p>
                            <p class="text-sm"
                                :class="adjustmentType === 'increase' ? 'text-status-success' : 'text-status-danger'">
                                {{ selectedProduct.name }}:
                                {{ adjustmentType === 'increase' ? '+' : '' }}{{ form.quantity }}
                                {{ selectedUnit ? selectedUnit.unit?.abbreviation : t('units.baseUnit') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <InputLabel for="notes" :value="t('common.notes')" />
                    <textarea id="notes" v-model="form.notes" rows="3"
                        class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"
                        placeholder="Reason for adjustment..."></textarea>
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link :href="route('inventory.index')"
                    class="rounded-lg bg-hover-wash px-4 py-2 text-sm font-medium text-muted hover:bg-line">
                {{ t('common.cancel') }}
                </Link>
                <PrimaryButton :disabled="form.processing">
                    {{ t('common.save') }}
                </PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>

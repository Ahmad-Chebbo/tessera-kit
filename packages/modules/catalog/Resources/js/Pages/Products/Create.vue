<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import FileUpload from '@ahmad-chebbo/tessera-ui/Components/FileUpload.vue';
import { Trash2 } from 'lucide-vue-next';
import IconButton from '@ahmad-chebbo/tessera-ui/Components/IconButton.vue';
import DataTable from '@ahmad-chebbo/tessera-ui/Components/DataTable.vue';

const props = defineProps({
    categories: Array,
    units: Array,
});

const { t } = useI18n();

const tableColumns = computed(() => [
    { key: 'unitName', label: t('units.unitName'), cellClass: 'px-4 py-2 text-sm text-fg', headerClass: 'px-4 py-2', wrap: true },
    { key: 'conversionFactor', label: t('units.conversionFactor'), cellClass: 'px-4 py-2', headerClass: 'px-4 py-2', wrap: true },
    { key: 'allowedFor', label: t('units.allowedFor'), cellClass: 'px-4 py-2', headerClass: 'px-4 py-2', wrap: true },
    { key: 'isBase', label: t('units.isBase'), cellClass: 'px-4 py-2', headerClass: 'px-4 py-2', wrap: true },
    { key: 'actions', label: t('common.actions'), align: 'end', cellClass: 'px-4 py-2', headerClass: 'px-4 py-2', wrap: true },
]);

const form = useForm({
    name: '',
    category_id: '',
    barcode: '',
    sku: '',
    base_price: '',
    sale_price: '',
    cost_price: '',
    currency: 'USD',
    unit: 'piece',
    description: '',
    is_active: true,
    unlimited_quantity: false,
    image: null,
    units: [],
});

// Unit management
const newUnit = ref({ unit_id: '', to_base_factor: 1, allowed_for: 'both', is_base: false });

const addUnit = () => {
    if (!newUnit.value.unit_id) return;
    if (form.units.find(u => u.unit_id == newUnit.value.unit_id)) return;

    const selectedUnit = props.units.find(u => u.id == newUnit.value.unit_id);
    form.units.push({
        unit_id: parseInt(newUnit.value.unit_id),
        unit_name: selectedUnit?.name || '',
        unit_abbreviation: selectedUnit?.abbreviation || '',
        to_base_factor: parseFloat(newUnit.value.to_base_factor) || 1,
        allowed_for: newUnit.value.allowed_for,
        is_base: newUnit.value.is_base,
    });

    newUnit.value = { unit_id: '', to_base_factor: 1, allowed_for: 'both', is_base: false };
};

const removeUnit = (index) => {
    form.units.splice(index, 1);
};

const setBaseUnit = (index) => {
    form.units.forEach((u, i) => {
        u.is_base = i === index;
        if (i === index) u.to_base_factor = 1;
    });
};

const submit = () => {
    form.post(route('products.store'));
};

</script>

<template>
    <Head :title="t('products.addProduct')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('products.addProduct') }}
                </h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 border border-line">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Name -->
                <div class="md:col-span-2">
                    <InputLabel for="name" :value="t('products.productName')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Category -->
                <div>
                    <InputLabel for="category_id" :value="t('products.category')" />
                    <Select id="category_id" v-model="form.category_id"
                        class="mt-1 w-full">
                        <option value="">{{ t('pos.selectCategory') }}</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.category_id" class="mt-2" />
                </div>

                <!-- Barcode -->
                <div>
                    <InputLabel for="barcode" :value="t('pos.barcode')" />
                    <TextInput id="barcode" v-model="form.barcode" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.barcode" class="mt-2" />
                </div>

                <!-- SKU -->
                <div>
                    <InputLabel for="sku" :value="t('pos.sku')" />
                    <TextInput id="sku" v-model="form.sku" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.sku" class="mt-2" />
                </div>

                <!-- Unit (legacy text field) -->
                <div>
                    <InputLabel for="unit" :value="t('pos.unit')" />
                    <TextInput id="unit" v-model="form.unit" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.unit" class="mt-2" />
                </div>

                <!-- Base Price -->
                <div>
                    <InputLabel for="base_price" :value="t('products.basePrice')" />
                    <TextInput id="base_price" v-model="form.base_price" type="number" step="0.01" min="0"
                        class="mt-1 block w-full" required />
                    <InputError :message="form.errors.base_price" class="mt-2" />
                </div>

                <!-- Sale Price -->
                <div>
                    <InputLabel for="sale_price" :value="t('products.salePrice')" />
                    <TextInput id="sale_price" v-model="form.sale_price" type="number" step="0.01" min="0"
                        class="mt-1 block w-full" />
                    <InputError :message="form.errors.sale_price" class="mt-2" />
                </div>

                <!-- Cost Price -->
                <div>
                    <InputLabel for="cost_price" :value="t('products.costPrice')" />
                    <TextInput id="cost_price" v-model="form.cost_price" type="number" step="0.01" min="0"
                        class="mt-1 block w-full" />
                    <InputError :message="form.errors.cost_price" class="mt-2" />
                </div>

                <!-- Currency -->
                <div>
                    <InputLabel for="currency" :value="t('products.currency')" />
                    <Select id="currency" v-model="form.currency"
                        class="mt-1 w-full">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="LBP">LBP</option>
                    </Select>
                    <InputError :message="form.errors.currency" class="mt-2" />
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <InputLabel for="description" :value="t('common.description')" />
                    <textarea id="description" v-model="form.description" rows="3"
                        class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <!-- Image -->
                <div class="md:col-span-2">
                    <InputLabel for="image" value="Image" />
                    <FileUpload id="image" v-model="form.image" :error="form.errors.image" class="mt-1" />
                </div>

                <!-- Active -->
                <div class="md:col-span-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.is_active"
                            class="rounded border-line text-accent focus:ring-ring-accent" />
                        <span class="text-sm text-muted">{{ t('products.active') }}</span>
                    </label>
                </div>

                <!-- Unlimited Quantity -->
                <div class="md:col-span-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.unlimited_quantity"
                            class="rounded border-line text-accent focus:ring-ring-accent" />
                        <span class="text-sm text-muted">{{ t('products.unlimitedQuantity') }}</span>
                    </label>
                </div>
            </div>

            <!-- Product Units Section -->
            <div v-if="units && units.length > 0" class="border-t border-line pt-6">
                <h3 class="font-display mb-4 text-lg font-normal text-fg">{{ t('units.productUnits') }}</h3>
                <p class="mb-4 text-sm text-muted">{{ t('units.productUnitsDescription') }}</p>

                <!-- Add Unit Row -->
                <div class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-hover-wash p-4">
                    <div class="flex-1 min-w-[150px]">
                        <InputLabel :value="t('units.selectUnit')" />
                        <Select v-model="newUnit.unit_id"
                            class="mt-1 w-full">
                            <option value="">{{ t('units.selectUnit') }}...</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id"
                                :disabled="form.units.some(u => u.unit_id == unit.id)">
                                {{ unit.name }} ({{ unit.abbreviation }})
                            </option>
                        </Select>
                    </div>
                    <div class="w-32">
                        <InputLabel :value="t('units.conversionFactor')" />
                        <TextInput v-model="newUnit.to_base_factor" type="number" step="0.0001" min="0.0001" class="mt-1 block w-full" />
                    </div>
                    <div class="w-36">
                        <InputLabel :value="t('units.allowedFor')" />
                        <Select v-model="newUnit.allowed_for"
                            class="mt-1 w-full">
                            <option value="both">{{ t('units.buyAndSell') }}</option>
                            <option value="buy">{{ t('units.buyOnly') }}</option>
                            <option value="sell">{{ t('units.sellOnly') }}</option>
                        </Select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-1 py-2">
                            <input type="checkbox" v-model="newUnit.is_base"
                                class="rounded border-line text-accent focus:ring-ring-accent" />
                            <span class="text-sm text-muted">{{ t('units.isBase') }}</span>
                        </label>
                    </div>
                    <button type="button" @click="addUnit"
                        class="rounded-lg bg-gold px-4 py-2 text-sm font-medium text-ink hover:bg-gold-deep">
                        {{ t('common.add') }}
                    </button>
                </div>

                <!-- Units List -->
                <div v-if="form.units.length > 0" class="overflow-x-auto rounded-lg border border-line">
                    <DataTable
                        :columns="tableColumns"
                        :rows="form.units"
                        :row-key="(pu, index) => index"
                        :card="false"
                    >
                        <template #cell-unitName="{ row: pu, index: index }">
                            {{ pu.unit_name }} ({{ pu.unit_abbreviation }})
                        </template>
                        <template #cell-conversionFactor="{ row: pu, index: index }">
                            <TextInput v-model="pu.to_base_factor" type="number" step="0.0001" min="0.0001"
                            class="w-28" :disabled="pu.is_base" />
                        </template>
                        <template #cell-allowedFor="{ row: pu, index: index }">
                            <Select v-model="pu.allowed_for">
                                <option value="both">{{ t('units.buyAndSell') }}</option>
                                <option value="buy">{{ t('units.buyOnly') }}</option>
                                <option value="sell">{{ t('units.sellOnly') }}</option>
                            </Select>
                        </template>
                        <template #cell-isBase="{ row: pu, index: index }">
                            <button type="button" @click="setBaseUnit(index)"
                                :class="[
                                    'rounded-full px-3 py-1 text-xs font-medium',
                                    pu.is_base
                                        ? 'bg-status-success-wash text-status-success'
                                        : 'bg-hover-wash text-muted hover:bg-line'
                                ]">
                                {{ pu.is_base ? t('units.baseUnit') : t('units.setAsBase') }}
                            </button>
                        </template>
                        <template #cell-actions="{ row: pu, index: index }">
                            <IconButton :icon="Trash2" :label="t('common.delete')" variant="danger" type="button" @click="removeUnit(index)" />
                        </template>
                    </DataTable>
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link :href="route('products.index')"
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

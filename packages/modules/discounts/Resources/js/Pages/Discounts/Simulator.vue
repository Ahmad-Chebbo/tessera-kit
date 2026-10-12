<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';
import Select from '@tessera/tessera-ui/Components/Select.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    products: Array,
    customers: Array,
});

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const lines = ref([{ product_id: '', quantity: 1 }]);
const customerId = ref('');
const coupon = ref('');
const paymentType = ref('cash');
const result = ref(null);
const errors = ref({});
const running = ref(false);

const productName = (id) => props.products.find((product) => product.id === id)?.name ?? id;
const couponMessage = computed(() => (result.value ? t(`discounts.couponStatus.${result.value.coupon_status}`) : ''));

const run = async () => {
    running.value = true;
    errors.value = {};

    try {
        const response = await axios.post(route('discounts.simulate'), {
            items: lines.value.filter((line) => line.product_id).map((line) => ({ product_id: line.product_id, quantity: line.quantity })),
            customer_id: customerId.value || null,
            coupon_code: coupon.value || null,
            payment_type: paymentType.value,
        });
        result.value = response.data;
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
        result.value = null;
    } finally {
        running.value = false;
    }
};
</script>

<template>
    <Head :title="t('discounts.simulatorTitle')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('discounts.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="font-display text-xl font-normal text-fg">{{ t('discounts.simulatorTitle') }}</h1>
                    <p class="text-sm text-muted">{{ t('discounts.simulatorSubtitle') }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2">
            <Card>
                <form class="space-y-5" @submit.prevent="run">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="sim_customer" :value="t('discounts.customer')" />
                            <Select id="sim_customer" v-model="customerId" class="mt-1 w-full">
                                <option value="">{{ t('discounts.noCustomer') }}</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                            </Select>
                        </div>
                        <div>
                            <InputLabel for="sim_coupon" :value="t('discounts.couponCode')" />
                            <TextInput id="sim_coupon" v-model="coupon" type="text" dir="ltr" class="mt-1 block w-full uppercase" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(line, index) in lines" :key="index" class="flex items-center gap-2">
                            <Select v-model="line.product_id" class="min-w-0 flex-1" :placeholder="t('discounts.product')">
                                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                            </Select>
                            <TextInput v-model="line.quantity" type="number" min="0.0001" step="any" class="w-24" :aria-label="t('discounts.quantity')" />
                            <button type="button" class="p-1 text-muted hover:text-status-danger" :aria-label="t('common.delete')" @click="lines.splice(index, 1)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        <button type="button" class="inline-flex items-center gap-1 text-sm text-accent" @click="lines.push({ product_id: '', quantity: 1 })">
                            <Plus class="h-4 w-4" />
                            {{ t('discounts.addLine') }}
                        </button>
                        <InputError :message="Object.values(errors)[0]?.[0]" />
                    </div>

                    <PrimaryButton :disabled="running">{{ t('discounts.run') }}</PrimaryButton>
                </form>
            </Card>

            <Card v-if="result">
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('discounts.result') }}</h2>
                <p v-if="couponMessage" class="mb-3 text-sm" :class="result.coupon_status === 'applied' ? 'text-status-success' : 'text-status-danger'">{{ couponMessage }}</p>

                <ul class="divide-y divide-line text-sm">
                    <li v-for="line in result.lines" :key="line.key" class="py-3">
                        <div class="flex justify-between gap-4">
                            <span class="font-medium text-fg">{{ productName(line.product_id) }}</span>
                            <span class="text-fg">{{ formatCurrency(line.net) }}</span>
                        </div>
                        <div v-for="applied in line.applied" :key="applied.rule_id" class="mt-1 flex items-center justify-between gap-2 text-xs text-muted">
                            <StatusBadge status="success">{{ applied.rule_name }}</StatusBadge>
                            <span>-{{ formatCurrency(applied.amount) }}</span>
                        </div>
                    </li>
                </ul>

                <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ t('discounts.subtotal') }}</dt><dd class="text-fg">{{ formatCurrency(result.subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ t('discounts.totalDiscount') }}</dt><dd class="text-fg">-{{ formatCurrency(result.total) }}</dd></div>
                    <div class="flex justify-between font-medium"><dt class="text-fg">{{ t('discounts.netTotal') }}</dt><dd class="text-fg">{{ formatCurrency(result.subtotal - result.total) }}</dd></div>
                </dl>
                <p v-if="!result.lines.some((line) => line.applied.length)" class="mt-3 text-sm text-muted">{{ t('discounts.noneApplied') }}</p>
            </Card>
        </div>
    </SidebarLayout>
</template>

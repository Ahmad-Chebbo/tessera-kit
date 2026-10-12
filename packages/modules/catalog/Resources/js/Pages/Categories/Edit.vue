<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import { Tag } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';

const props = defineProps({
    category: Object,
    parentCategories: Array,
});

const { t } = useI18n();

const form = useForm({
    name: props.category.name,
    parent_id: props.category.parent_id || '',
    description: props.category.description || '',
    color: props.category.color || '#6366f1', // allow-colour: stored default
    icon: props.category.icon || '',
});

const submit = () => {
    form.put(route('categories.update', props.category.id));
};

const colors = [
    '#6366f1', // Indigo allow-colour
    '#8b5cf6', // Violet allow-colour
    '#a21caf', // Purple allow-colour
    '#be185d', // Rose allow-colour
    '#ec4899', // Pink allow-colour
    '#ef4444', // Red allow-colour
    '#f97316', // Orange allow-colour
    '#eab308', // Yellow allow-colour
    '#facc15', // Amber allow-colour
    '#22c55e', // Green allow-colour
    '#10b981', // Emerald allow-colour
    '#14b8a6', // Teal allow-colour
    '#06b6d4', // Cyan allow-colour
    '#0ea5e9', // Sky allow-colour
    '#3b82f6', // Blue allow-colour
    '#0284c7', // Light Blue allow-colour
    '#0e7490', // Dark Cyan allow-colour
    '#0d9488', // Dark Teal allow-colour
    '#57534e', // Stone allow-colour
    '#9ca3af', // Gray allow-colour
];

const icons = [
  // 🍽️ Food & Meals
  '🍔','🌭','🍕','🍟','🌮','🌯','🥪','🍗','🍖','🥩','🍛','🍲','🥘',
  '🍝','🍜','🍣','🍱','🍤','🥟','🍚','🍙','🍘','🥗',
  // 🥐 Bakery & Snacks
  '🍞','🥖','🥨','🥯','🥐','🥞','🧇','🍩','🍪','🎂','🍰','🧁',
  '🥧','🍿','🥜','🌰','🥠','🍫','🍬','🍭',
  // 🍎 Produce & Fresh
  '🍎','🍏','🍌','🍉','🍇','🍓','🍒','🍑','🍐','🍍','🥭','🍋',
  '🍅','🥑','🥕','🌽','🥔','🥒','🥬','🌶️','🧄','🧅','🍆',
  // 🥛 Dairy & Cold
  '🥛','🧈','🧀','🥚','🍦','🍧','🍨',
  // 🥤 Drinks & Bar
  '🥤','🧃','🧋','☕','🍵','🧉','🍺','🍻','🍷','🥂','🥃',
  '🍸','🍹','🍶','🍼','🧊',
  // 🛒 Grocery / Retail
  '🛒','🛍️','📦','🏷️','🧾','⚖️','📊',
  // 🧼 Household & Essentials
  '🧻','🧴','🧽','🪣','🪥','🧼','🪒',
  // 💄 Beauty & Personal Care
  '💄','💅','🪮','🧴','🧼',
  // 👕 Apparel & Accessories
  '👕','👖','👗','👚','🧥','👟','🧢','🧦','👜','🎒','👒',
  // 💊 Pharmacy & Health
  '💊','🩹','🌡️','🧴',
  // 🧰 Hardware / Services
  '🛠️','🔧','🔩','🪛','🔨','🧰',
  // 💳 Payments & POS
  '💳','💵','💰','🪙','🏦','📱','🖨️','🧾',
  // 🎁 Misc / Extras
  '🎁','⭐','🔥','✅','➕','➖','❌','♻️'
];


</script>

<template>
    <Head :title="t('categories.editCategory')" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <h2 class="font-display text-xl font-normal leading-tight text-fg">
                    {{ t('categories.editCategory') }}
                </h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6 rounded-2xl bg-surface p-6 border border-line">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Name -->
                <div>
                    <InputLabel for="name" :value="t('categories.categoryName')" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Parent Category -->
                <div>
                    <InputLabel for="parent_id" :value="t('categories.parentCategory')" />
                    <Select id="parent_id" v-model="form.parent_id"
                        class="mt-1 w-full">
                        <option value="">{{ t('common.noResults') }}</option>
                        <option v-for="cat in parentCategories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </Select>
                    <InputError :message="form.errors.parent_id" class="mt-2" />
                </div>

                <!-- Color -->
                <div class="md:col-span-2">
                    <InputLabel value="Color" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button v-for="color in colors" :key="color" type="button" @click="form.color = color"
                            class="h-8 w-8 rounded-lg transition-transform" :style="{ backgroundColor: color }"
                            :class="{ 'ring-2 ring-offset-2 ring-line scale-110': form.color === color }"></button>
                    </div>
                    <InputError :message="form.errors.color" class="mt-2" />
                </div>

                <!-- Icons -->
                <div class="md:col-span-2">
                    <InputLabel value="Icon" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="icon in icons"
                            :key="icon"
                            type="button"
                            @click="form.icon = icon"
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-hover-wash border border-line hover:bg-line transition-transform"
                            :class="{ 'ring-2 ring-offset-2 ring-line scale-110': form.icon === icon }"
                        >
                            <span class="text-xl">{{ icon }}</span>
                        </button>
                    </div>
                    <InputError :message="form.errors.icon" class="mt-2" />
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <InputLabel for="description" :value="t('common.description')" />
                    <textarea id="description" v-model="form.description" rows="3"
                        class="mt-1 block w-full rounded-lg border-line focus:border-gold-on focus:ring-ring-accent"></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <!-- Preview -->
                <div class="md:col-span-2">
                    <InputLabel value="Preview" />
                    <div class="mt-2 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg"
                            :style="{ backgroundColor: form.color }">
                            <span v-if="form.icon" class="text-xl text-white">{{ form.icon }}</span> <!-- allow-colour: icon over user-chosen colour -->
                            <Tag v-else class="h-6 w-6 text-white" /> <!-- allow-colour: icon over user-chosen colour -->
                        </div>
                        <span class="font-medium text-fg">
                            {{ form.name || 'Category Name' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link :href="route('categories.index')"
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

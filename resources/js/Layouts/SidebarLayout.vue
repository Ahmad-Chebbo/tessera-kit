<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    ArrowLeftRight,
    Boxes,
    Building,
    Building2,
    ChartColumn,
    ChartLine,
    ChevronDown,
    ChevronsLeft,
    ChevronsRight,
    CircleDollarSign,
    LayoutDashboard,
    ListOrdered,
    MapPin,
    Menu,
    Monitor,
    Package,
    Receipt,
    Scale,
    Settings,
    ShoppingCart,
    Tag,
    Truck,
    Users,
    UsersRound,
    X,
    Activity,
    CalendarDays,
    Wallet,
    FolderDown,
    Group,
    Percent,
    CreditCard,
    Layers,
    ShieldCheck,
    Clock,
    Landmark,
    Lock,
    LockOpen,
} from 'lucide-vue-next';
import LanguageToggle from '@tessera/tessera-ui/Components/LanguageToggle.vue';
import ScrollArea from '@tessera/tessera-ui/Components/ScrollArea.vue';
import ThemeToggle from '@tessera/tessera-ui/Components/ThemeToggle.vue';
import AttendanceToggle from '@/Components/AttendanceToggle.vue';
import Dropdown from '@tessera/tessera-ui/Components/Dropdown.vue';
import DropdownLink from '@tessera/tessera-ui/Components/DropdownLink.vue';
import ToastContainer from '@tessera/tessera-ui/Components/ToastContainer.vue';
import ConfirmDialog from '@tessera/tessera-ui/Components/ConfirmDialog.vue';
import { useNavigation } from '@/Composables/useNavigation';

const { t } = useI18n();
const page = usePage();

const COLLAPSE_KEY = 'sidebar-collapsed';

function readCollapsed() {
    try {
        return localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
}

const collapsed = ref(readCollapsed());

function toggleCollapsed() {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(COLLAPSE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Persisting is optional.
    }
}
const mobileMenuOpen = ref(false);

const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.auth.permissions || []);
const currentBranch = computed(() => page.props.currentBranch);
const posShift = computed(() => page.props.posShift);
const requirePosShift = computed(() => page.props.requirePosShift);

const hasPermission = (permission) => {
    if (!user.value) return false;
    // Super Admin has all permissions
    const userRoles = user.value.roles || [];
    if (userRoles.some(role => role.name === 'Super Admin')) return true;
    return permissions.value.includes(permission);
};

const navigation = useNavigation();

const iconComponents = {
    home: LayoutDashboard,
    terminal: Monitor,
    package: Package,
    tag: Tag,
    inventory: Boxes,
    truck: Truck,
    'shopping-cart': ShoppingCart,
    transfer: ArrowLeftRight,
    chart: ChartColumn,
    receipt: Receipt,
    cog: Settings,
    currency: CircleDollarSign,
    branch: Building,
    location: MapPin,
    tenant: Building2,
    users: Users,
    scale: Scale,
    'users-group': UsersRound,
    'list-chart': ListOrdered,
    profit: ChartLine,
    activity: Activity,
    calendar: CalendarDays,
    wallet: Wallet,
    download: FolderDown,
    group: Group,
    percent: Percent,
    plans: Layers,
    subscription: CreditCard,
    shield: ShieldCheck,
    clock: Clock,
    drawer: Landmark,
};

const getIcon = (icon) => iconComponents[icon] || LayoutDashboard;

const linkClasses = (active) => [
    'relative flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium transition-colors duration-150 ease-qayema',
    active
        ? 'bg-accent-wash text-accent before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded-full before:bg-gold-on'
        : 'text-muted hover:bg-hover-wash hover:text-fg',
    collapsed.value ? 'lg:justify-center lg:px-0' : '',
];
</script>

<template>
    <div class="min-h-screen bg-bg text-fg">
        <button
            type="button"
            class="fixed start-3 top-3 z-50 rounded-control border border-line bg-surface p-2 text-fg lg:hidden"
            :aria-label="t('nav.openMenu')"
            @click="mobileMenuOpen = !mobileMenuOpen"
        >
            <component :is="mobileMenuOpen ? X : Menu" class="h-5 w-5" />
        </button>

        <aside
            :class="[
                'fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-bg transition-[width,transform] duration-300 ease-qayema',
                collapsed ? 'lg:w-[72px]' : 'lg:w-64',
                mobileMenuOpen
                    ? 'translate-x-0'
                    : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center justify-center border-b border-line px-4">
                <span
                    class="truncate font-display text-lg font-normal uppercase tracking-[0.3em] text-accent"
                >
                    <span v-html="collapsed ? 'S' : '<img src=\'/images/logo.png\' alt=\'Logo\' />'"></span>                </span>
            </div>

            <ScrollArea class="min-h-0 flex-1">
            <nav class="space-y-1 px-3 py-4">
                <template v-for="(item, index) in navigation" :key="index">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        :title="collapsed ? item.name : undefined"
                        :class="linkClasses(item.active)"
                        @click="mobileMenuOpen = false"
                    >
                        <component :is="getIcon(item.icon)" class="h-[18px] w-[18px] shrink-0" />
                        <span :class="collapsed ? 'lg:hidden' : ''" class="truncate">{{ item.name }}</span>
                    </Link>

                    <div v-else-if="item.section" class="pt-4">
                        <p
                            :class="collapsed ? 'lg:hidden' : ''"
                            class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-faint"
                        >
                            {{ item.section }}
                        </p>
                        <div v-if="collapsed" class="mx-3 mb-2 hidden border-t border-line-2 lg:block"></div>
                        <Link
                            v-for="subItem in item.items"
                            :key="subItem.name"
                            :href="subItem.href"
                            :title="collapsed ? subItem.name : undefined"
                            :class="linkClasses(subItem.active)"
                            @click="mobileMenuOpen = false"
                        >
                            <component :is="getIcon(subItem.icon)" class="h-[18px] w-[18px] shrink-0" />
                            <span :class="collapsed ? 'lg:hidden' : ''" class="truncate">{{ subItem.name }}</span>
                        </Link>
                    </div>
                </template>
            </nav>
            </ScrollArea>

            <div class="hidden shrink-0 border-t border-line p-3 lg:block">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-control px-3 py-2.5 text-sm text-muted transition-colors duration-150 ease-qayema hover:bg-hover-wash hover:text-fg"
                    :class="collapsed ? 'justify-center px-0' : ''"
                    :title="collapsed ? t('nav.expand') : t('nav.collapse')"
                    @click="toggleCollapsed"
                >
                    <component
                        :is="collapsed ? ChevronsRight : ChevronsLeft"
                        class="h-[18px] w-[18px] shrink-0 rtl:rotate-180"
                    />
                    <span v-if="!collapsed">{{ t('nav.collapse') }}</span>
                </button>
            </div>
        </aside>

        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <div
            class="transition-[padding] duration-300 ease-qayema"
            :class="collapsed ? 'lg:ps-[72px]' : 'lg:ps-64'"
        >
            <header class="sticky top-0 z-20 border-b border-line bg-bg/90 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-4 ps-14 pe-4 sm:pe-6 lg:ps-8 lg:pe-8">
                    <div class="min-w-0 font-display text-xl font-normal text-fg">
                        <slot name="header">
                            <h1 class="truncate">{{ t('nav.dashboard') }}</h1>
                        </slot>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                        <div
                            v-if="currentBranch"
                            class="hidden max-w-48 items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-sm text-muted sm:flex"
                        >
                            <Building2 class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ currentBranch?.name }}</span>
                        </div>

                        <Link
                            v-if="hasPermission('open pos shift') && (posShift || requirePosShift)"
                            :href="route('pos-shifts.index')"
                            class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-sm sm:flex"
                            :class="posShift ? 'border-status-success-border bg-status-success-wash text-status-success' : 'border-status-warn-border bg-status-warn-wash text-status-warn'"
                        >
                            <component :is="posShift ? LockOpen : Lock" class="h-4 w-4" />
                            {{ posShift ? t('shifts.pos.chip') : t('shifts.pos.chipClosed') }}
                        </Link>

                        <AttendanceToggle />
                        <LanguageToggle />
                        <ThemeToggle />

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-full border border-line bg-surface py-1 ps-1 pe-2 text-sm text-muted transition-colors duration-150 ease-qayema hover:bg-hover-wash"
                                >
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-accent-fill text-xs font-semibold text-ink"
                                    >
                                        {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </span>
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </template>

                            <template #content>
                                <div class="border-b border-line-2 px-4 py-2">
                                    <p class="truncate text-sm font-medium text-fg">{{ user?.name }}</p>
                                    <p class="truncate text-xs text-faint">{{ user?.email }}</p>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    {{ t('nav.profile') }}
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    {{ t('nav.logout') }}
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
        <ToastContainer />
        <ConfirmDialog />
    </div>
</template>

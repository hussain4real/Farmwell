<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Package,
    Plus,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import {
    index as inventoryIndex,
    store as storeItem,
} from '@/routes/inventory';
import { store as storeMovement } from '@/routes/inventory/movements';
import type { FarmOption, FarmOperationPermissions, Team } from '@/types';

type InventoryItemRow = {
    id: number;
    farmId: number | null;
    farmName: string | null;
    name: string;
    category: string;
    categoryLabel: string;
    sku: string | null;
    unit: string;
    unitWeightKg: string | null;
    currentQuantity: string;
    reorderLevel: string;
    isLowStock: boolean;
    unitCostMinor: number;
    unitCost: string;
    stockValueMinor: number;
    stockValue: string;
    currency: string;
    supplierName: string | null;
    notes: string | null;
    isActive: boolean;
};

type InventoryMovementRow = {
    id: number;
    inventoryItemId: number;
    itemName: string;
    itemUnit: string;
    farmName: string | null;
    productionCycleName: string | null;
    movementType: string;
    movementTypeLabel: string;
    isDeduction: boolean;
    quantity: string;
    totalCost: string;
    currency: string;
    movementDate: string;
    reference: string | null;
    notes: string | null;
    recordedBy: string | null;
};

const props = defineProps<{
    permissions: FarmOperationPermissions;
    currency: string;
    summary: {
        totalItems: number;
        lowStockCount: number;
        totalStockValue: string;
        recentUsageCost: string;
    };
    items: InventoryItemRow[];
    movements: InventoryMovementRow[];
    farms: {
        id: number;
        name: string;
        productionCycles: { id: number; name: string }[];
    }[];
    options: {
        categories: FarmOption[];
        movementTypes: FarmOption[];
    };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
            {
                title: 'Stock & Feed',
                href: props.currentTeam
                    ? inventoryIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(
    () => (page.props.currentTeam as Team | null)?.slug ?? '',
);

const activeCategory = ref('all');
const search = ref('');
const createDrawerOpen = ref(false);
const movementDrawerOpen = ref(false);
const selectedItem = ref<InventoryItemRow | null>(null);
const movementType = ref('purchase');
const movementFarmId = ref('');
const recordAsExpense = ref(true);

const today = new Date().toISOString().slice(0, 10);

const filteredItems = computed(() =>
    props.items.filter((item) => {
        if (
            activeCategory.value !== 'all' &&
            item.category !== activeCategory.value
        ) {
            return false;
        }

        if (
            search.value.trim() &&
            !item.name.toLowerCase().includes(search.value.trim().toLowerCase())
        ) {
            return false;
        }

        return true;
    }),
);

const selectedFarm = computed(() =>
    props.farms.find((f) => String(f.id) === movementFarmId.value),
);

const openMovementFor = (
    item: InventoryItemRow,
    type: 'purchase' | 'usage',
) => {
    selectedItem.value = item;
    movementType.value = type;
    movementFarmId.value = item.farmId
        ? String(item.farmId)
        : props.farms[0]?.id
          ? String(props.farms[0].id)
          : '';
    movementDrawerOpen.value = true;
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Stock & Feed Inventory" />

        <PageHero
            eyebrow="Inventory Control"
            title="Feed, seeds & farm inputs"
            description="Track stock on hand, restock purchases, daily feed/fertilizer consumption, and low-stock alerts."
        >
            <template #actions>
                <Button
                    v-if="permissions.canManageFarmOperations"
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="createDrawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Add stock item
                </Button>
            </template>
        </PageHero>

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <MetricCard
                label="Active stock items"
                :value="summary.totalItems"
                :icon="Package"
                tone="emerald"
            />
            <MetricCard
                label="Total stock value"
                :value="formatCurrency(summary.totalStockValue, currency)"
                :icon="Warehouse"
            />
            <MetricCard
                label="Low-stock alerts"
                :value="summary.lowStockCount"
                :icon="AlertTriangle"
                :tone="summary.lowStockCount > 0 ? 'rose' : 'default'"
            />
            <MetricCard
                label="30-day usage cost"
                :value="formatCurrency(summary.recentUsageCost, currency)"
                :icon="ArrowDownRight"
                tone="amber"
            />
        </section>

        <!-- Filters -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
        >
            <div
                class="no-scrollbar -mx-4 flex min-w-0 flex-nowrap gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
            >
                <button
                    type="button"
                    class="min-h-9 shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        activeCategory === 'all'
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-card hover:bg-accent'
                    "
                    @click="activeCategory = 'all'"
                >
                    All
                </button>
                <button
                    v-for="cat in options.categories"
                    :key="cat.value"
                    type="button"
                    class="min-h-9 shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        activeCategory === cat.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-card hover:bg-accent'
                    "
                    @click="activeCategory = cat.value"
                >
                    {{ cat.label }}
                </button>
            </div>
            <Input
                v-model="search"
                placeholder="Search items..."
                class="w-full sm:w-64"
            />
        </div>

        <section
            v-if="filteredItems.length > 0"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="item in filteredItems"
                :key="item.id"
                class="flex flex-col justify-between rounded-2xl border bg-card p-5 shadow-xs"
            >
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-semibold">{{ item.name }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ item.categoryLabel }}
                                <span v-if="item.farmName">
                                    · {{ item.farmName }}
                                </span>
                            </p>
                        </div>
                        <Badge
                            :variant="
                                item.isLowStock ? 'destructive' : 'secondary'
                            "
                        >
                            {{ item.isLowStock ? 'Low stock' : 'In stock' }}
                        </Badge>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3 text-sm"
                    >
                        <div>
                            <span class="block text-xs text-muted-foreground">
                                Available
                            </span>
                            <strong class="font-semibold tabular-nums">
                                {{ item.currentQuantity }} {{ item.unit }}
                            </strong>
                        </div>
                        <div>
                            <span class="block text-xs text-muted-foreground">
                                Value ({{
                                    formatCurrency(
                                        item.unitCost,
                                        item.currency,
                                    )
                                }}/{{ item.unit }})
                            </span>
                            <strong class="font-semibold tabular-nums">
                                {{
                                    formatCurrency(
                                        item.stockValue,
                                        item.currency,
                                    )
                                }}
                            </strong>
                        </div>
                    </div>
                </div>

                <div
                    v-if="permissions.canManageFieldWork"
                    class="mt-4 flex flex-wrap gap-2 border-t pt-3"
                >
                    <Button
                        v-if="permissions.canManageFarmOperations"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="flex-1"
                        @click="openMovementFor(item, 'purchase')"
                    >
                        <ArrowUpRight class="mr-1 size-3.5 text-emerald-600" />
                        Restock
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="flex-1"
                        @click="openMovementFor(item, 'usage')"
                    >
                        <ArrowDownRight class="mr-1 size-3.5 text-amber-600" />
                        Log usage
                    </Button>
                </div>
            </article>
        </section>

        <EmptyState
            v-else
            title="No inventory items match your filter"
            description="Add feed bags, seeds, fertilizers, vaccines, or packaging crates to track consumption automatically."
            :icon="Package"
        >
            <template #action>
                <Button
                    v-if="permissions.canManageFarmOperations"
                    type="button"
                    @click="createDrawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Add stock item
                </Button>
            </template>
        </EmptyState>

        <!-- Movement ledger -->
        <section class="space-y-3 rounded-2xl border bg-card p-5">
            <h2 class="text-base font-semibold">Recent stock movements</h2>
            <div v-if="movements.length > 0" class="divide-y">
                <div
                    v-for="move in movements"
                    :key="move.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                >
                    <div>
                        <p class="font-medium">
                            {{ move.itemName }} · {{ move.movementTypeLabel }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ move.movementDate }}
                            <span v-if="move.farmName">
                                · {{ move.farmName }}</span
                            >
                            <span v-if="move.productionCycleName">
                                · {{ move.productionCycleName }}
                            </span>
                        </p>
                    </div>
                    <div class="text-right tabular-nums">
                        <p
                            class="font-semibold"
                            :class="
                                move.isDeduction
                                    ? 'text-amber-700 dark:text-amber-300'
                                    : 'text-emerald-700 dark:text-emerald-300'
                            "
                        >
                            {{ move.isDeduction ? '-' : '+'
                            }}{{ move.quantity }}
                            {{ move.itemUnit }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatCurrency(move.totalCost, move.currency) }}
                        </p>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No stock movements recorded yet.
            </p>
        </section>

        <!-- Create Item Drawer -->
        <SlideOverForm
            :open="createDrawerOpen"
            title="Add stock item"
            description="Track feed, seeds, fertilizers, vaccines, or equipment"
            @update:open="createDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="storeItem.form(teamSlug)"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="createDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="item-name">Item name</Label>
                    <Input
                        id="item-name"
                        name="name"
                        placeholder="e.g. Broiler Starter Mash"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="item-category">Category</Label>
                    <select
                        id="item-category"
                        name="category"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="cat in options.categories"
                            :key="cat.value"
                            :value="cat.value"
                        >
                            {{ cat.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="item-farm">Farm (optional)</Label>
                    <select
                        id="item-farm"
                        name="farm_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">Shared across all farms</option>
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="farm.id"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="item-unit">Unit</Label>
                        <Input
                            id="item-unit"
                            name="unit"
                            placeholder="bags, kg, L"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="item-weight">Kg per bag (optional)</Label>
                        <Input
                            id="item-weight"
                            name="unit_weight_kg"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="0.01"
                            placeholder="25"
                        />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="item-qty">Opening stock</Label>
                        <Input
                            id="item-qty"
                            name="current_quantity"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            default-value="0"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="item-reorder">Low-stock alert at</Label>
                        <Input
                            id="item-reorder"
                            name="reorder_level"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            default-value="5"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="item-cost">Unit cost ({{ currency }})</Label>
                    <Input
                        id="item-cost"
                        name="unit_cost"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        default-value="0"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="item-supplier">Supplier</Label>
                    <Input id="item-supplier" name="supplier_name" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Save stock item</Button
                >
            </Form>
        </SlideOverForm>

        <!-- Record Movement Drawer -->
        <SlideOverForm
            :open="movementDrawerOpen"
            :title="
                selectedItem
                    ? `Record movement: ${selectedItem.name}`
                    : 'Record movement'
            "
            description="Purchases add stock; usage/spoilage deducts stock"
            @update:open="movementDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug && selectedItem"
                v-bind="storeMovement.form([teamSlug, selectedItem.id])"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="movementDrawerOpen = false"
            >
                <input
                    type="hidden"
                    name="record_as_expense"
                    :value="recordAsExpense ? '1' : '0'"
                />
                <div class="grid gap-1.5">
                    <Label for="move-type">Movement type</Label>
                    <select
                        id="move-type"
                        v-model="movementType"
                        name="movement_type"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="t in options.movementTypes"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="move-qty">
                            Quantity ({{ selectedItem.unit }})
                        </Label>
                        <Input
                            id="move-qty"
                            name="quantity"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="0.01"
                            required
                        />
                        <InputError :message="errors.quantity" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="move-date">Date</Label>
                        <Input
                            id="move-date"
                            name="movement_date"
                            type="date"
                            :default-value="today"
                            required
                        />
                    </div>
                </div>
                <div v-if="movementType === 'purchase'" class="grid gap-1.5">
                    <Label for="move-cost">Unit cost ({{ currency }})</Label>
                    <Input
                        id="move-cost"
                        name="unit_cost"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        :default-value="selectedItem.unitCost"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="move-farm">Farm</Label>
                    <select
                        id="move-farm"
                        v-model="movementFarmId"
                        name="farm_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="String(farm.id)"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="move-cycle">Cycle / batch (optional)</Label>
                    <select
                        id="move-cycle"
                        name="production_cycle_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="cycle in selectedFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <label
                    v-if="
                        movementType === 'purchase' &&
                        permissions.canManageFinance &&
                        movementFarmId
                    "
                    class="flex items-center gap-2 rounded-lg border bg-muted/40 p-3 text-sm"
                >
                    <input
                        v-model="recordAsExpense"
                        type="checkbox"
                        class="size-4"
                    />
                    <span>Also record purchase in Farm Finance expenses</span>
                </label>
                <div class="grid gap-1.5">
                    <Label for="move-notes">Notes</Label>
                    <Input id="move-notes" name="notes" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                >
                    Confirm stock movement
                </Button>
            </Form>
        </SlideOverForm>
    </div>
</template>

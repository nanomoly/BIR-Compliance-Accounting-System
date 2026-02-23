<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, toRef } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useQueryTabSync } from '@/composables/useQueryTabSync';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type InventoryItem = {
    id: number;
    sku: string;
    name: string;
    unit: string;
    quantity_on_hand: number;
    reorder_level: number | null;
    is_active: boolean;
};

type InventoryMovement = {
    id: number;
    inventory_item_id: number;
    movement_date: string;
    movement_type: 'in' | 'out' | 'adjustment_in' | 'adjustment_out';
    quantity: number;
    unit_cost: number | null;
    remarks: string | null;
    inventory_item?: {
        id: number;
        sku: string;
        name: string;
        unit: string;
    };
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Inventory', href: '/cas/inventory' },
];

const state = reactive({
    activeTab: 'item-master',
    items: [] as InventoryItem[],
    movements: [] as InventoryMovement[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    movementPage: 1,
    movementLastPage: 1,
    perPage: 15,
    total: 0,
    movementTotal: 0,
    loading: false,
    movementLoading: false,
    saving: false,
    deleting: false,
    editingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const { syncFromQuery } = useQueryTabSync(toRef(state, 'activeTab'), ['item-master', 'stock-movements']);

const form = reactive({
    sku: '',
    name: '',
    unit: 'pcs',
    quantity_on_hand: 0,
    reorder_level: 0,
    is_active: true,
});

const editForm = reactive({
    sku: '',
    name: '',
    unit: 'pcs',
    quantity_on_hand: 0,
    reorder_level: 0,
    is_active: true,
});

const movementForm = reactive({
    inventory_item_id: 0,
    movement_date: new Date().toISOString().slice(0, 10),
    movement_type: 'in' as InventoryMovement['movement_type'],
    quantity: 1,
    unit_cost: 0,
    remarks: '',
});

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            data: InventoryItem[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        }>(`/api/inventory-items?per_page=${state.perPage}&page=${page}`);

        state.items = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load inventory.';
    } finally {
        state.loading = false;
    }
}

async function loadMovements(page = 1) {
    state.movementLoading = true;
    state.error = '';

    try {
        const response = await api.get<{
            data: InventoryMovement[];
            current_page: number;
            last_page: number;
            total: number;
        }>(`/api/inventory-movements?per_page=${state.perPage}&page=${page}`);

        state.movements = response.data;
        state.movementPage = response.current_page;
        state.movementLastPage = response.last_page;
        state.movementTotal = response.total;
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load inventory movements.';
    } finally {
        state.movementLoading = false;
    }
}

function exportInventory() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/inventory?${query.toString()}`, '_blank');
}

async function createItem() {
    if (!can('inventory.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/inventory-items', {
            sku: form.sku,
            name: form.name,
            unit: form.unit,
            quantity_on_hand: form.quantity_on_hand,
            reorder_level: form.reorder_level || null,
            is_active: form.is_active,
        });

        state.success = 'Inventory item created.';
        form.sku = '';
        form.name = '';
        form.unit = 'pcs';
        form.quantity_on_hand = 0;
        form.reorder_level = 0;
        form.is_active = true;
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create inventory item.';
    } finally {
        state.saving = false;
    }
}

function startEdit(item: InventoryItem) {
    state.editingId = item.id;
    editForm.sku = item.sku;
    editForm.name = item.name;
    editForm.unit = item.unit;
    editForm.quantity_on_hand = item.quantity_on_hand;
    editForm.reorder_level = item.reorder_level ?? 0;
    editForm.is_active = item.is_active;
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveEdit(itemId: number) {
    if (!can('inventory.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/inventory-items/${itemId}`, {
            sku: editForm.sku,
            name: editForm.name,
            unit: editForm.unit,
            quantity_on_hand: editForm.quantity_on_hand,
            reorder_level: editForm.reorder_level || null,
            is_active: editForm.is_active,
        });

        state.success = 'Inventory item updated.';
        state.editingId = 0;
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to update inventory item.';
    } finally {
        state.saving = false;
    }
}

async function deleteItem(itemId: number) {
    if (!can('inventory.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/inventory-items/${itemId}`);
        state.success = 'Inventory item deleted.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete inventory item.';
    } finally {
        state.deleting = false;
    }
}

async function recordMovement() {
    if (!can('inventory.update')) {
        return;
    }

    if (!movementForm.inventory_item_id) {
        state.error = 'Please select an item.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/inventory-movements', {
            inventory_item_id: movementForm.inventory_item_id,
            movement_date: movementForm.movement_date,
            movement_type: movementForm.movement_type,
            quantity: movementForm.quantity,
            unit_cost: movementForm.unit_cost || null,
            remarks: movementForm.remarks || null,
        });

        state.success = 'Inventory movement recorded.';
        movementForm.quantity = 1;
        movementForm.unit_cost = 0;
        movementForm.remarks = '';
        await Promise.all([loadData(state.currentPage), loadMovements(1)]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to record movement.';
    } finally {
        state.saving = false;
    }
}

onMounted(async () => {
    syncFromQuery();

    await Promise.all([loadData(), loadMovements()]);
});
</script>

<template>
    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'item-master' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'item-master'">Item Master</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'stock-movements' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'stock-movements'">Stock Movements</button>
            </div>

            <template v-if="state.activeTab === 'item-master'">
                <SectionCard title="Add Inventory Item" description="Maintain item masters and quantity on hand.">
                    <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createItem">
                        <input v-model="form.sku" required placeholder="SKU" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.name" required placeholder="Item name" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.unit" required placeholder="Unit" class="rounded border px-3 py-2 text-sm" />
                        <input v-model.number="form.quantity_on_hand" type="number" step="0.01" min="0" placeholder="On hand" class="rounded border px-3 py-2 text-sm" />
                        <input v-model.number="form.reorder_level" type="number" step="0.01" min="0" placeholder="Reorder level" class="rounded border px-3 py-2 text-sm" />
                        <label class="flex items-center gap-2 rounded border px-3 py-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" />
                            Active
                        </label>
                        <button v-if="can('inventory.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                            {{ state.saving ? 'Saving...' : 'Create Item' }}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Inventory List">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                        <div class="flex items-end gap-2">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">From</label>
                                <input v-model="state.exportFromDate" type="date" class="rounded border px-2 py-2 text-sm" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">To</label>
                                <input v-model="state.exportToDate" type="date" class="rounded border px-2 py-2 text-sm" />
                            </div>
                            <button v-if="can('inventory.view')" type="button" class="rounded border px-3 py-2 text-sm" @click="exportInventory">Export Excel</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">SKU</th>
                                    <th class="px-2 py-2 text-left">Name</th>
                                    <th class="px-2 py-2 text-left">Unit</th>
                                    <th class="px-2 py-2 text-left">On Hand</th>
                                    <th class="px-2 py-2 text-left">Reorder</th>
                                    <th class="px-2 py-2 text-left">Status</th>
                                    <th class="px-2 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in state.items" :key="item.id" class="border-b">
                                    <template v-if="state.editingId === item.id">
                                        <td class="px-2 py-2"><input v-model="editForm.sku" class="w-full rounded border px-2 py-1" /></td>
                                        <td class="px-2 py-2"><input v-model="editForm.name" class="w-full rounded border px-2 py-1" /></td>
                                        <td class="px-2 py-2"><input v-model="editForm.unit" class="w-full rounded border px-2 py-1" /></td>
                                        <td class="px-2 py-2"><input v-model.number="editForm.quantity_on_hand" type="number" step="0.01" class="w-full rounded border px-2 py-1" /></td>
                                        <td class="px-2 py-2"><input v-model.number="editForm.reorder_level" type="number" step="0.01" class="w-full rounded border px-2 py-1" /></td>
                                        <td class="px-2 py-2"><input v-model="editForm.is_active" type="checkbox" /></td>
                                        <td class="px-2 py-2">
                                            <div class="flex gap-2">
                                                <button v-if="can('inventory.update')" type="button" class="rounded border px-2 py-1" :disabled="state.saving" @click="saveEdit(item.id)">Save</button>
                                                <button type="button" class="rounded border px-2 py-1" @click="cancelEdit">Cancel</button>
                                            </div>
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td class="px-2 py-2">{{ item.sku }}</td>
                                        <td class="px-2 py-2">{{ item.name }}</td>
                                        <td class="px-2 py-2">{{ item.unit }}</td>
                                        <td class="px-2 py-2">{{ item.quantity_on_hand }}</td>
                                        <td class="px-2 py-2">{{ item.reorder_level ?? '-' }}</td>
                                        <td class="px-2 py-2">{{ item.is_active ? 'Active' : 'Inactive' }}</td>
                                        <td class="px-2 py-2">
                                            <div class="flex gap-2">
                                                <button v-if="can('inventory.update')" type="button" class="rounded border px-2 py-1" @click="startEdit(item)">Edit</button>
                                                <button v-if="can('inventory.delete')" type="button" class="rounded border px-2 py-1" :disabled="state.deleting" @click="deleteItem(item.id)">Delete</button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage <= 1" @click="loadData(state.currentPage - 1)">Previous</button>
                        <span class="text-sm text-muted-foreground">Page {{ state.currentPage }} of {{ state.lastPage }}</span>
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage >= state.lastPage" @click="loadData(state.currentPage + 1)">Next</button>
                    </div>
                </SectionCard>
            </template>

            <template v-if="state.activeTab === 'stock-movements'">
                <SectionCard title="Record Inventory Movement" description="Post stock-in, stock-out, and adjustments with audit trail.">
                    <form class="grid gap-3 md:grid-cols-3" @submit.prevent="recordMovement">
                        <select v-model.number="movementForm.inventory_item_id" required class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select item</option>
                            <option v-for="item in state.items" :key="item.id" :value="item.id">{{ item.sku }} - {{ item.name }}</option>
                        </select>
                        <input v-model="movementForm.movement_date" required type="date" class="rounded border px-3 py-2 text-sm" />
                        <select v-model="movementForm.movement_type" required class="rounded border px-3 py-2 text-sm">
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                            <option value="adjustment_in">Adjustment In</option>
                            <option value="adjustment_out">Adjustment Out</option>
                        </select>
                        <input v-model.number="movementForm.quantity" required type="number" step="0.01" min="0.01" placeholder="Quantity" class="rounded border px-3 py-2 text-sm" />
                        <input v-model.number="movementForm.unit_cost" type="number" step="0.01" min="0" placeholder="Unit cost (optional)" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="movementForm.remarks" placeholder="Remarks (optional)" class="rounded border px-3 py-2 text-sm" />
                        <button v-if="can('inventory.update')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                            {{ state.saving ? 'Saving...' : 'Record Movement' }}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Inventory Movements">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Total: {{ state.movementTotal }}</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">Date</th>
                                    <th class="px-2 py-2 text-left">Item</th>
                                    <th class="px-2 py-2 text-left">Type</th>
                                    <th class="px-2 py-2 text-left">Qty</th>
                                    <th class="px-2 py-2 text-left">Unit Cost</th>
                                    <th class="px-2 py-2 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="movement in state.movements" :key="movement.id" class="border-b">
                                    <td class="px-2 py-2">{{ formatPhDateOnly(movement.movement_date) }}</td>
                                    <td class="px-2 py-2">{{ movement.inventory_item?.sku }} - {{ movement.inventory_item?.name }}</td>
                                    <td class="px-2 py-2 uppercase">{{ movement.movement_type }}</td>
                                    <td class="px-2 py-2">{{ movement.quantity }}</td>
                                    <td class="px-2 py-2">{{ movement.unit_cost != null ? formatAmount(movement.unit_cost) : '-' }}</td>
                                    <td class="px-2 py-2">{{ movement.remarks ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.movementPage <= 1" @click="loadMovements(state.movementPage - 1)">Previous</button>
                        <span class="text-sm text-muted-foreground">Page {{ state.movementPage }} of {{ state.movementLastPage }}</span>
                        <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.movementPage >= state.movementLastPage" @click="loadMovements(state.movementPage + 1)">Next</button>
                    </div>
                </SectionCard>
            </template>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading inventory...</p>
            <p v-if="state.movementLoading" class="text-sm text-muted-foreground">Loading inventory movements...</p>
        </div>
    </AppLayout>
</template>

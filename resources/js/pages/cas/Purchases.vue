<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SimpleRef = {
    id: number;
    code?: string;
    name: string;
};

type PurchaseOrderLine = {
    description: string;
    quantity: number;
    unit_price: number;
    line_total?: number;
};

type PurchaseOrder = {
    id: number;
    order_number: string;
    order_date: string;
    due_date: string | null;
    status: string;
    subtotal: number;
    vat_amount: number;
    total_amount: number;
    supplier?: { id: number; code: string; name: string };
    branch?: { id: number; code: string; name: string };
    invoice?: { id: number; invoice_number: string; status: string } | null;
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Purchase Orders', href: '/cas/purchases' },
];

const state = reactive({
    orders: [] as PurchaseOrder[],
    suppliers: [] as SimpleRef[],
    branches: [] as SimpleRef[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    saving: false,
    processingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const form = reactive({
    branch_id: 0,
    supplier_id: 0,
    order_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    vat_amount: 0,
    remarks: '',
    lines: [
        { description: '', quantity: 1, unit_price: 0 },
    ] as PurchaseOrderLine[],
});

const subtotal = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0),
);

const totalAmount = computed(() => subtotal.value + Number(form.vat_amount || 0));

function addLine() {
    form.lines.push({ description: '', quantity: 1, unit_price: 0 });
}

function removeLine(index: number) {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function exportPurchases() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/purchase-orders?${query.toString()}`, '_blank');
}

async function loadCatalogs() {
    const [suppliersRes, branchesRes] = await Promise.all([
        api.get<{ data: Array<{ id: number; code: string; name: string }> }>('/api/suppliers?per_page=100'),
        api.get<{ data: Array<{ id: number; code: string; name: string }> }>('/api/branches?per_page=100'),
    ]);

    state.suppliers = suppliersRes.data;
    state.branches = branchesRes.data;

    if (!form.supplier_id && state.suppliers.length > 0) {
        form.supplier_id = state.suppliers[0].id;
    }

    if (!form.branch_id && state.branches.length > 0) {
        form.branch_id = state.branches[0].id;
    }
}

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            data: PurchaseOrder[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        }>(`/api/purchase-orders?per_page=${state.perPage}&page=${page}`);

        state.orders = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load purchase orders.';
    } finally {
        state.loading = false;
    }
}

async function createOrder() {
    if (!can('purchases.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/purchase-orders', {
            branch_id: form.branch_id,
            supplier_id: form.supplier_id,
            order_date: form.order_date,
            due_date: form.due_date || null,
            vat_amount: form.vat_amount,
            remarks: form.remarks || null,
            lines: form.lines.map((line) => ({
                description: line.description,
                quantity: line.quantity,
                unit_price: line.unit_price,
            })),
        });

        state.success = 'Purchase order created.';
        form.order_date = new Date().toISOString().slice(0, 10);
        form.due_date = '';
        form.vat_amount = 0;
        form.remarks = '';
        form.lines = [{ description: '', quantity: 1, unit_price: 0 }];
        await loadData(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create purchase order.';
    } finally {
        state.saving = false;
    }
}

async function receiveOrder(id: number) {
    if (!can('purchases.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/purchase-orders/${id}/receive`, {});
        state.success = 'Purchase order received.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to receive purchase order.';
    } finally {
        state.processingId = 0;
    }
}

async function convertToBill(id: number) {
    if (!can('purchases.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/purchase-orders/${id}/convert-to-bill`, {});
        state.success = 'Purchase order converted to draft supplier bill.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to convert purchase order.';
    } finally {
        state.processingId = 0;
    }
}

async function deleteOrder(id: number) {
    if (!can('purchases.delete')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/purchase-orders/${id}`);
        state.success = 'Purchase order deleted.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete purchase order.';
    } finally {
        state.processingId = 0;
    }
}

onMounted(async () => {
    state.loading = true;

    try {
        await Promise.all([loadCatalogs(), loadData()]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to initialize purchases module.';
    } finally {
        state.loading = false;
    }
});
</script>

<template>
    <Head title="Purchase Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard title="Create Purchase Order" description="Capture supplier order details before receiving and billing.">
                <form class="grid gap-3" @submit.prevent="createOrder">
                    <div class="grid gap-3 md:grid-cols-4">
                        <select v-model.number="form.branch_id" required class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select branch</option>
                            <option v-for="branch in state.branches" :key="branch.id" :value="branch.id">
                                {{ branch.code }} - {{ branch.name }}
                            </option>
                        </select>
                        <select v-model.number="form.supplier_id" required class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select supplier</option>
                            <option v-for="supplier in state.suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.code }} - {{ supplier.name }}
                            </option>
                        </select>
                        <input v-model="form.order_date" required type="date" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.due_date" type="date" class="rounded border px-3 py-2 text-sm" />
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/30">
                                    <th class="px-2 py-2 text-left">Description</th>
                                    <th class="px-2 py-2 text-left">Quantity</th>
                                    <th class="px-2 py-2 text-left">Unit Price</th>
                                    <th class="px-2 py-2 text-left">Line Total</th>
                                    <th class="px-2 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-b">
                                    <td class="px-2 py-2">
                                        <input v-model="line.description" required class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.quantity" required type="number" min="0.01" step="0.01" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.unit_price" required type="number" min="0" step="0.01" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">{{ formatAmount(Number(line.quantity || 0) * Number(line.unit_price || 0)) }}</td>
                                    <td class="px-2 py-2">
                                        <button type="button" class="rounded border px-2 py-1" @click="removeLine(index)">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded border px-3 py-2 text-sm" @click="addLine">Add Line</button>
                        <input v-model.number="form.vat_amount" type="number" min="0" step="0.01" placeholder="VAT amount" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.remarks" placeholder="Remarks (optional)" class="min-w-[220px] rounded border px-3 py-2 text-sm" />
                        <span class="text-sm text-muted-foreground">Subtotal: {{ formatAmount(subtotal) }}</span>
                        <span class="text-sm text-muted-foreground">Total: {{ formatAmount(totalAmount) }}</span>
                        <button v-if="can('purchases.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                            {{ state.saving ? 'Saving...' : 'Create Purchase Order' }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Purchase Orders">
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
                        <button v-if="can('purchases.view')" type="button" class="rounded border px-3 py-2 text-sm" @click="exportPurchases">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Order #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Supplier</th>
                                <th class="px-2 py-2 text-left">Branch</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Total</th>
                                <th class="px-2 py-2 text-left">Bill</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in state.orders" :key="order.id" class="border-b">
                                <td class="px-2 py-2">{{ order.order_number }}</td>
                                <td class="px-2 py-2">{{ formatPhDateOnly(order.order_date) }}</td>
                                <td class="px-2 py-2">{{ order.supplier?.code }} - {{ order.supplier?.name }}</td>
                                <td class="px-2 py-2">{{ order.branch?.code }} - {{ order.branch?.name }}</td>
                                <td class="px-2 py-2 uppercase">{{ order.status }}</td>
                                <td class="px-2 py-2">{{ formatAmount(order.total_amount) }}</td>
                                <td class="px-2 py-2">{{ order.invoice?.invoice_number ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="can('purchases.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === order.id || !['draft', 'ordered'].includes(order.status)"
                                            @click="receiveOrder(order.id)"
                                        >
                                            Receive
                                        </button>
                                        <button
                                            v-if="can('purchases.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === order.id || !['received', 'billed'].includes(order.status)"
                                            @click="convertToBill(order.id)"
                                        >
                                            To Bill
                                        </button>
                                        <button
                                            v-if="can('purchases.delete')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === order.id || order.status !== 'draft'"
                                            @click="deleteOrder(order.id)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage <= 1" @click="loadData(state.currentPage - 1)">
                        Previous
                    </button>
                    <span class="text-sm text-muted-foreground">Page {{ state.currentPage }} of {{ state.lastPage }}</span>
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.currentPage >= state.lastPage" @click="loadData(state.currentPage + 1)">
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading purchase orders...</p>
        </div>
    </AppLayout>
</template>

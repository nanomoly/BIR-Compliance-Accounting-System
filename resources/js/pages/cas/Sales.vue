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

type SalesOrderLine = {
    description: string;
    quantity: number;
    unit_price: number;
    line_total?: number;
};

type SalesOrder = {
    id: number;
    order_number: string;
    order_date: string;
    due_date: string | null;
    status: string;
    subtotal: number;
    vat_amount: number;
    total_amount: number;
    customer?: { id: number; code: string; name: string };
    branch?: { id: number; code: string; name: string };
    invoice?: { id: number; invoice_number: string; status: string } | null;
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Sales Orders', href: '/cas/sales' },
];

const state = reactive({
    orders: [] as SalesOrder[],
    customers: [] as SimpleRef[],
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

function exportSales() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/sales-orders?${query.toString()}`, '_blank');
}

const form = reactive({
    branch_id: 0,
    customer_id: 0,
    order_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    vat_amount: 0,
    remarks: '',
    lines: [
        { description: '', quantity: 1, unit_price: 0 },
    ] as SalesOrderLine[],
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

async function loadCatalogs() {
    const [customersRes, branchesRes] = await Promise.all([
        api.get<{ data: Array<{ id: number; code: string; name: string }> }>('/api/customers?per_page=100'),
        api.get<{ data: Array<{ id: number; code: string; name: string }> }>('/api/branches?per_page=100'),
    ]);

    state.customers = customersRes.data;
    state.branches = branchesRes.data;

    if (!form.customer_id && state.customers.length > 0) {
        form.customer_id = state.customers[0].id;
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
            data: SalesOrder[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        }>(`/api/sales-orders?per_page=${state.perPage}&page=${page}`);

        state.orders = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load sales orders.';
    } finally {
        state.loading = false;
    }
}

async function createOrder() {
    if (!can('sales.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/sales-orders', {
            branch_id: form.branch_id,
            customer_id: form.customer_id,
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

        state.success = 'Sales order created.';
        form.order_date = new Date().toISOString().slice(0, 10);
        form.due_date = '';
        form.vat_amount = 0;
        form.remarks = '';
        form.lines = [{ description: '', quantity: 1, unit_price: 0 }];
        await loadData(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create sales order.';
    } finally {
        state.saving = false;
    }
}

async function confirmOrder(id: number) {
    if (!can('sales.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/sales-orders/${id}/confirm`, {});
        state.success = 'Sales order confirmed.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to confirm sales order.';
    } finally {
        state.processingId = 0;
    }
}

async function convertToInvoice(id: number) {
    if (!can('sales.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/sales-orders/${id}/convert-to-invoice`, {});
        state.success = 'Sales order converted to draft invoice.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to convert sales order.';
    } finally {
        state.processingId = 0;
    }
}

async function deleteOrder(id: number) {
    if (!can('sales.delete')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/sales-orders/${id}`);
        state.success = 'Sales order deleted.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to delete sales order.';
    } finally {
        state.processingId = 0;
    }
}

onMounted(async () => {
    state.loading = true;

    try {
        await Promise.all([loadCatalogs(), loadData()]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to initialize sales module.';
    } finally {
        state.loading = false;
    }
});
</script>

<template>
    <Head title="Sales Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard title="Create Sales Order" description="Capture order details and line items before invoicing.">
                <form class="grid gap-3" @submit.prevent="createOrder">
                    <div class="grid gap-3 md:grid-cols-4">
                        <select v-model.number="form.branch_id" required class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select branch</option>
                            <option v-for="branch in state.branches" :key="branch.id" :value="branch.id">
                                {{ branch.code }} - {{ branch.name }}
                            </option>
                        </select>
                        <select v-model.number="form.customer_id" required class="rounded border px-3 py-2 text-sm">
                            <option :value="0" disabled>Select customer</option>
                            <option v-for="customer in state.customers" :key="customer.id" :value="customer.id">
                                {{ customer.code }} - {{ customer.name }}
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
                        <button v-if="can('sales.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                            {{ state.saving ? 'Saving...' : 'Create Sales Order' }}
                        </button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Sales Orders">
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
                        <button v-if="can('sales.view')" type="button" class="rounded border px-3 py-2 text-sm" @click="exportSales">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Order #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Customer</th>
                                <th class="px-2 py-2 text-left">Branch</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Total</th>
                                <th class="px-2 py-2 text-left">Invoice</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in state.orders" :key="order.id" class="border-b">
                                <td class="px-2 py-2">{{ order.order_number }}</td>
                                <td class="px-2 py-2">{{ formatPhDateOnly(order.order_date) }}</td>
                                <td class="px-2 py-2">{{ order.customer?.code }} - {{ order.customer?.name }}</td>
                                <td class="px-2 py-2">{{ order.branch?.code }} - {{ order.branch?.name }}</td>
                                <td class="px-2 py-2 uppercase">{{ order.status }}</td>
                                <td class="px-2 py-2">{{ formatAmount(order.total_amount) }}</td>
                                <td class="px-2 py-2">{{ order.invoice?.invoice_number ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="can('sales.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === order.id || order.status !== 'draft'"
                                            @click="confirmOrder(order.id)"
                                        >
                                            Confirm
                                        </button>
                                        <button
                                            v-if="can('sales.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === order.id || !['confirmed', 'invoiced'].includes(order.status)"
                                            @click="convertToInvoice(order.id)"
                                        >
                                            To Invoice
                                        </button>
                                        <button
                                            v-if="can('sales.delete')"
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

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading sales orders...</p>
        </div>
    </AppLayout>
</template>

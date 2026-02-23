<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, watch } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'E-Invoicing', href: '/cas/e-invoicing' },
];

const form = reactive({
    branch_id: 1,
    customer_id: null as number | null,
    supplier_id: null as number | null,
    journal_entry_id: null as number | null,
    invoice_type: 'sales',
    invoice_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    vat_amount: 0,
    remarks: '',
    lines: [
        {
            description: '',
            quantity: 1,
            unit_price: 0,
        },
    ],
});

const state = reactive({
    invoices: [] as Array<any>,
    customers: [] as Array<{ id: number; code: string; name: string }>,
    suppliers: [] as Array<{ id: number; code: string; name: string }>,
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    error: '',
    success: '',
});

useStateNotifications(state);

const lineSubtotal = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity) * Number(line.unit_price), 0),
);

const invoiceTotal = computed(() => lineSubtotal.value + Number(form.vat_amount || 0));

const canSubmit = computed(() => {
    const hasValidLines =
        form.lines.length > 0 &&
        form.lines.every(
            (line) =>
                line.description.trim().length > 0 &&
                Number(line.quantity) > 0 &&
                Number(line.unit_price) >= 0,
        );

    const hasParty =
        form.invoice_type === 'purchase'
            ? Boolean(form.supplier_id)
            : Boolean(form.customer_id);

    return hasValidLines && hasParty && form.invoice_date.length > 0;
});

function lineAmount(index: number): number {
    const line = form.lines[index];
    if (!line) {
        return 0;
    }

    return Number(line.quantity) * Number(line.unit_price);
}

function addLine() {
    form.lines.push({ description: '', quantity: 1, unit_price: 0 });
}

function removeLine(index: number) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

watch(
    () => form.invoice_type,
    (invoiceType) => {
        if (invoiceType === 'purchase') {
            form.customer_id = null;
            return;
        }

        form.supplier_id = null;
    },
);

async function loadInvoices(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const [invoiceResponse, customerResponse, supplierResponse] =
            await Promise.all([
                api.get<{
                    data: Array<any>;
                    current_page: number;
                    last_page: number;
                    per_page: number;
                    total: number;
                }>(`/api/e-invoices?per_page=${state.perPage}&page=${page}`),
                api.get<{
                    data: Array<{ id: number; code: string; name: string }>;
                }>('/api/customers?per_page=200'),
                api.get<{
                    data: Array<{ id: number; code: string; name: string }>;
                }>('/api/suppliers?per_page=200'),
            ]);

        state.invoices = invoiceResponse.data;
        state.currentPage = invoiceResponse.current_page;
        state.lastPage = invoiceResponse.last_page;
        state.perPage = invoiceResponse.per_page;
        state.total = invoiceResponse.total;
        state.customers = customerResponse.data;
        state.suppliers = supplierResponse.data;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load invoices.';
    } finally {
        state.loading = false;
    }
}

function exportInvoices() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/e-invoices?${query.toString()}`, '_blank');
}

async function createInvoice() {
    if (!can('e_invoices.create')) {
        return;
    }

    state.error = '';
    state.success = '';

    if (!canSubmit.value) {
        state.error =
            'Complete the required party and line item fields before creating a draft.';
        return;
    }

    try {
        await api.post('/api/e-invoices', {
            ...form,
            due_date: form.due_date || null,
            remarks: form.remarks || null,
        });
        state.success = 'E-invoice draft created.';
        form.remarks = '';
        form.lines = [{ description: '', quantity: 1, unit_price: 0 }];
        await loadInvoices();
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to create e-invoice.';
    }
}

async function issueInvoice(invoiceId: number) {
    state.error = '';

    try {
        await api.post(`/api/e-invoices/${invoiceId}/issue`);
        state.success = 'E-invoice issued and locked.';
        await loadInvoices();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to issue invoice.';
    }
}

async function transmitInvoice(invoiceId: number) {
    state.error = '';

    try {
        await api.post(`/api/e-invoices/${invoiceId}/transmit`);
        state.success = 'E-invoice transmitted (simulated BIR EIS payload).';
        await loadInvoices();
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to transmit invoice.';
    }
}

async function cancelInvoice(invoiceId: number) {
    state.error = '';

    try {
        await api.post(`/api/e-invoices/${invoiceId}/cancel`);
        state.success = 'E-invoice cancelled.';
        await loadInvoices();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to cancel invoice.';
    }
}

function printInvoice(invoiceId: number) {
    window.open(`/api/e-invoices/${invoiceId}/print`, '_blank');
}

onMounted(loadInvoices);
</script>

<template>
    <Head title="E-Invoicing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Create E-Invoice Draft"
                description="Issue to lock invoice, then transmit to generate EIS reference."
            >
                <form class="grid gap-3" @submit.prevent="createInvoice">
                    <div class="grid gap-4 rounded-md border p-3 md:grid-cols-4">
                        <div class="grid gap-2">
                            <Label for="invoice-type">Invoice Type</Label>
                            <select
                                id="invoice-type"
                                v-model="form.invoice_type"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
                            >
                                <option value="sales">Sales Invoice</option>
                                <option value="service">Service Invoice</option>
                                <option value="purchase">Purchase / AP Invoice</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="invoice-date">Invoice Date</Label>
                            <Input id="invoice-date" v-model="form.invoice_date" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="due-date">Due Date</Label>
                            <Input id="due-date" v-model="form.due_date" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="vat-amount">VAT Amount</Label>
                            <Input
                                id="vat-amount"
                                v-model.number="form.vat_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 rounded-md border p-3 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="customer-id">Customer</Label>
                            <select
                                id="customer-id"
                                v-model.number="form.customer_id"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 py-1 text-sm outline-none focus-visible:ring-[3px] disabled:opacity-50"
                                :disabled="form.invoice_type === 'purchase'"
                            >
                                <option :value="null">
                                    {{ form.invoice_type === 'purchase' ? 'Customer not required for purchase' : 'Select customer' }}
                                </option>
                                <option
                                    v-for="customer in state.customers"
                                    :key="customer.id"
                                    :value="customer.id"
                                >
                                    {{ customer.code }} - {{ customer.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="supplier-id">Supplier</Label>
                            <select
                                id="supplier-id"
                                v-model.number="form.supplier_id"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 py-1 text-sm outline-none focus-visible:ring-[3px] disabled:opacity-50"
                                :disabled="form.invoice_type !== 'purchase'"
                            >
                                <option :value="null">
                                    {{ form.invoice_type === 'purchase' ? 'Select supplier' : 'Supplier used for purchase/AP invoices' }}
                                </option>
                                <option v-for="supplier in state.suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.code }} - {{ supplier.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="invoice-remarks">Remarks</Label>
                            <Input id="invoice-remarks" v-model="form.remarks" placeholder="Reference details or notes" />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/40">
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">Description</th>
                                    <th class="px-2 py-2 text-left">Qty</th>
                                    <th class="px-2 py-2 text-left">Unit Price</th>
                                    <th class="px-2 py-2 text-left">Line Total</th>
                                    <th class="px-2 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-b">
                                    <td class="px-2 py-2">
                                        <Input v-model="line.description" required placeholder="Item or service description" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <Input v-model.number="line.quantity" type="number" min="0.01" step="0.01" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <Input v-model.number="line.unit_price" type="number" min="0" step="0.01" />
                                    </td>
                                    <td class="px-2 py-2 font-medium">
                                        {{ formatAmount(lineAmount(index)) }}
                                    </td>
                                    <td class="px-2 py-2">
                                        <Button type="button" variant="outline" size="sm" @click="removeLine(index)">
                                            Remove
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 rounded-md border bg-muted/20 p-3 md:grid-cols-3">
                        <p class="text-sm">
                            Subtotal: <span class="font-semibold">{{ formatAmount(lineSubtotal) }}</span>
                        </p>
                        <p class="text-sm">
                            VAT: <span class="font-semibold">{{ formatAmount(form.vat_amount) }}</span>
                        </p>
                        <p class="text-sm">
                            Invoice Total: <span class="font-semibold">{{ formatAmount(invoiceTotal) }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="addLine">Add Line</Button>
                        <Button v-if="can('e_invoices.create')" type="submit" :disabled="!canSubmit">Create Draft</Button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="E-Invoices">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                    <div class="flex items-end gap-2">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium">From</label>
                            <input
                                v-model="state.exportFromDate"
                                type="date"
                                class="rounded border px-2 py-2 text-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium">To</label>
                            <input
                                v-model="state.exportToDate"
                                type="date"
                                class="rounded border px-2 py-2 text-sm"
                            />
                        </div>
                        <Button
                            v-if="can('e_invoices.view')"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="exportInvoices"
                        >
                            Export Excel
                        </Button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40">
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Invoice #</th>
                                <th class="px-2 py-2 text-left">Control #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Total</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in state.invoices" :key="invoice.id" class="border-b">
                                <td class="px-2 py-2">{{ invoice.invoice_number }}</td>
                                <td class="px-2 py-2">{{ invoice.control_number }}</td>
                                <td class="px-2 py-2">{{ formatPhDateOnly(invoice.invoice_date) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(Number(invoice.total_amount)) }}</td>
                                <td class="px-2 py-2">
                                    <span class="rounded-full bg-muted px-2 py-1 text-xs font-medium uppercase">
                                        {{ invoice.status }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <Button v-if="invoice.status === 'draft'" variant="outline" size="sm" @click="issueInvoice(invoice.id)">
                                            Issue
                                        </Button>
                                        <Button v-if="invoice.status === 'issued'" variant="outline" size="sm" @click="transmitInvoice(invoice.id)">
                                            Transmit
                                        </Button>
                                        <Button v-if="invoice.status === 'issued'" variant="outline" size="sm" @click="cancelInvoice(invoice.id)">
                                            Cancel
                                        </Button>
                                        <Button variant="outline" size="sm" @click="printInvoice(invoice.id)">
                                            Print
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="state.currentPage <= 1"
                        @click="loadInvoices(state.currentPage - 1)"
                    >
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Page {{ state.currentPage }} of {{ state.lastPage }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="state.currentPage >= state.lastPage"
                        @click="loadInvoices(state.currentPage + 1)"
                    >
                        Next
                    </Button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
        </div>
    </AppLayout>
</template>

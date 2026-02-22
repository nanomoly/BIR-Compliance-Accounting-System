<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, watch } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();

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
    loading: false,
    error: '',
    success: '',
});

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

async function loadInvoices() {
    state.loading = true;
    state.error = '';

    try {
        const [invoiceResponse, customerResponse, supplierResponse] =
            await Promise.all([
                api.get<{ data: Array<any> }>('/api/e-invoices?per_page=100'),
                api.get<{
                    data: Array<{ id: number; code: string; name: string }>;
                }>('/api/customers?per_page=200'),
                api.get<{
                    data: Array<{ id: number; code: string; name: string }>;
                }>('/api/suppliers?per_page=200'),
            ]);

        state.invoices = invoiceResponse.data;
        state.customers = customerResponse.data;
        state.suppliers = supplierResponse.data;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load invoices.';
    } finally {
        state.loading = false;
    }
}

async function createInvoice() {
    state.error = '';
    state.success = '';

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
                    <div class="grid gap-3 md:grid-cols-4">
                        <select v-model="form.invoice_type" class="rounded border px-3 py-2 text-sm">
                            <option value="sales">Sales Invoice</option>
                            <option value="service">Service Invoice</option>
                            <option value="purchase">Purchase / AP Invoice</option>
                        </select>
                        <input v-model="form.invoice_date" type="date" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.due_date" type="date" class="rounded border px-3 py-2 text-sm" />
                        <input v-model.number="form.vat_amount" type="number" step="0.01" min="0" placeholder="VAT" class="rounded border px-3 py-2 text-sm" />
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <select
                            v-model.number="form.customer_id"
                            class="rounded border px-3 py-2 text-sm"
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
                        <select v-model.number="form.supplier_id" class="rounded border px-3 py-2 text-sm" :disabled="form.invoice_type !== 'purchase'">
                            <option :value="null">
                                {{ form.invoice_type === 'purchase' ? 'Select supplier' : 'Supplier used for purchase/AP invoices' }}
                            </option>
                            <option v-for="supplier in state.suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.code }} - {{ supplier.name }}
                            </option>
                        </select>
                        <input v-model="form.remarks" placeholder="Remarks" class="rounded border px-3 py-2 text-sm" />
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">Description</th>
                                    <th class="px-2 py-2 text-left">Qty</th>
                                    <th class="px-2 py-2 text-left">Unit Price</th>
                                    <th class="px-2 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-b">
                                    <td class="px-2 py-2">
                                        <input v-model="line.description" required class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.quantity" type="number" min="0.01" step="0.01" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <button type="button" class="rounded border px-2 py-1 text-xs" @click="removeLine(index)">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" class="rounded border px-3 py-2 text-sm" @click="addLine">Add Line</button>
                        <button type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground">Create Draft</button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="E-Invoices">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
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
                                <td class="px-2 py-2">{{ invoice.invoice_date }}</td>
                                <td class="px-2 py-2">{{ invoice.total_amount }}</td>
                                <td class="px-2 py-2 uppercase">{{ invoice.status }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <button v-if="invoice.status === 'draft'" class="rounded border px-2 py-1 text-xs" @click="issueInvoice(invoice.id)">
                                            Issue
                                        </button>
                                        <button v-if="invoice.status === 'issued'" class="rounded border px-2 py-1 text-xs" @click="transmitInvoice(invoice.id)">
                                            Transmit
                                        </button>
                                        <button v-if="invoice.status === 'issued'" class="rounded border px-2 py-1 text-xs" @click="cancelInvoice(invoice.id)">
                                            Cancel
                                        </button>
                                        <button class="rounded border px-2 py-1 text-xs" @click="printInvoice(invoice.id)">
                                            Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
            <p v-if="state.error" class="text-sm text-destructive">{{ state.error }}</p>
            <p v-if="state.success" class="text-sm text-emerald-600">{{ state.success }}</p>
        </div>
    </AppLayout>
</template>

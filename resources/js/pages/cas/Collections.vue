<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type InvoiceOption = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    total_amount: number;
    paid_amount: number;
    balance_due: number;
    customer?: { id: number; code: string; name: string };
    branch?: { id: number; code: string; name: string };
};

type SalesReceipt = {
    id: number;
    receipt_number: string;
    receipt_date: string;
    amount: number;
    payment_method: string;
    reference_no: string | null;
    remarks: string | null;
    invoice?: { id: number; invoice_number: string; total_amount: number };
    customer?: { id: number; code: string; name: string };
    branch?: { id: number; code: string; name: string };
    journal_entry?: { id: number; entry_number: string; status: string; posted_at: string | null };
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Collections', href: '/cas/collections' },
];

const state = reactive({
    invoices: [] as InvoiceOption[],
    receipts: [] as SalesReceipt[],
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    saving: false,
    error: '',
    success: '',
});

useStateNotifications(state);

const form = reactive({
    invoice_id: 0,
    receipt_date: new Date().toISOString().slice(0, 10),
    amount: 0,
    payment_method: 'cash',
    reference_no: '',
    remarks: '',
});

function selectedInvoice(): InvoiceOption | undefined {
    return state.invoices.find((invoice) => invoice.id === form.invoice_id);
}

async function loadCatalog() {
    const response = await api.get<{ invoices: InvoiceOption[] }>('/api/collections/catalog');
    state.invoices = response.invoices;

    if (!form.invoice_id && state.invoices.length > 0) {
        form.invoice_id = state.invoices[0].id;
        form.amount = Number(state.invoices[0].balance_due || 0);
    }
}

async function loadReceipts(page = 1) {
    const response = await api.get<{
        data: SalesReceipt[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    }>(`/api/collections/receipts?per_page=${state.perPage}&page=${page}`);

    state.receipts = response.data;
    state.currentPage = response.current_page;
    state.lastPage = response.last_page;
    state.perPage = response.per_page;
    state.total = response.total;
}

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        await Promise.all([loadCatalog(), loadReceipts(page)]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to load collections data.';
    } finally {
        state.loading = false;
    }
}

function onInvoiceChange() {
    const invoice = selectedInvoice();
    if (!invoice) {
        return;
    }

    form.amount = Number(invoice.balance_due || 0);
}

function exportCollections() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/collections-receipts?${query.toString()}`, '_blank');
}

async function createReceipt() {
    if (!can('collections.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/collections/receipts', {
            invoice_id: form.invoice_id,
            receipt_date: form.receipt_date,
            amount: form.amount,
            payment_method: form.payment_method,
            reference_no: form.reference_no || null,
            remarks: form.remarks || null,
        });

        state.success = 'Collection receipt issued.';
        form.reference_no = '';
        form.remarks = '';
        await loadData(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to issue receipt.';
    } finally {
        state.saving = false;
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Collections" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard title="Issue Collection Receipt" description="Apply payment to an issued sales/service invoice.">
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createReceipt">
                    <select v-model.number="form.invoice_id" required class="rounded border px-3 py-2 text-sm" @change="onInvoiceChange">
                        <option :value="0" disabled>Select invoice</option>
                        <option v-for="invoice in state.invoices" :key="invoice.id" :value="invoice.id">
                            {{ invoice.invoice_number }} • {{ invoice.customer?.code }} - {{ invoice.customer?.name }} • Bal {{ formatAmount(invoice.balance_due) }}
                        </option>
                    </select>
                    <input v-model="form.receipt_date" required type="date" class="rounded border px-3 py-2 text-sm" />
                    <input v-model.number="form.amount" required type="number" min="0.01" step="0.01" placeholder="Amount" class="rounded border px-3 py-2 text-sm" />
                    <select v-model="form.payment_method" class="rounded border px-3 py-2 text-sm">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="check">Check</option>
                        <option value="online">Online</option>
                    </select>
                    <input v-model="form.reference_no" placeholder="Reference #" class="rounded border px-3 py-2 text-sm" />
                    <input v-model="form.remarks" placeholder="Remarks" class="rounded border px-3 py-2 text-sm" />
                    <button v-if="can('collections.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                        {{ state.saving ? 'Saving...' : 'Issue Receipt' }}
                    </button>
                </form>

                <div v-if="selectedInvoice()" class="mt-3 rounded border p-3 text-sm">
                    <p>Selected Invoice: <span class="font-medium">{{ selectedInvoice()?.invoice_number }}</span></p>
                    <p>Total: {{ formatAmount(selectedInvoice()?.total_amount) }} | Paid: {{ formatAmount(selectedInvoice()?.paid_amount) }} | Balance: {{ formatAmount(selectedInvoice()?.balance_due) }}</p>
                </div>
            </SectionCard>

            <SectionCard title="Receipts">
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
                        <button v-if="can('collections.view')" type="button" class="rounded border px-3 py-2 text-sm" @click="exportCollections">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Receipt #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Invoice</th>
                                <th class="px-2 py-2 text-left">Customer</th>
                                <th class="px-2 py-2 text-left">Method</th>
                                <th class="px-2 py-2 text-left">Amount</th>
                                <th class="px-2 py-2 text-left">Reference</th>
                                <th class="px-2 py-2 text-left">Journal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="receipt in state.receipts" :key="receipt.id" class="border-b">
                                <td class="px-2 py-2">{{ receipt.receipt_number }}</td>
                                <td class="px-2 py-2">{{ formatPhDateOnly(receipt.receipt_date) }}</td>
                                <td class="px-2 py-2">{{ receipt.invoice?.invoice_number ?? '-' }}</td>
                                <td class="px-2 py-2">{{ receipt.customer?.code }} - {{ receipt.customer?.name }}</td>
                                <td class="px-2 py-2 uppercase">{{ receipt.payment_method }}</td>
                                <td class="px-2 py-2">{{ formatAmount(receipt.amount) }}</td>
                                <td class="px-2 py-2">{{ receipt.reference_no ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    {{ receipt.journal_entry?.entry_number ?? '-' }}
                                    <span v-if="receipt.journal_entry?.status" class="uppercase text-muted-foreground">({{ receipt.journal_entry.status }})</span>
                                </td>
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

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading collections...</p>
        </div>
    </AppLayout>
</template>

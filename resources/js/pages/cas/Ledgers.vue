<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Subsidiary Ledgers', href: '/cas/ledgers' },
];

const state = reactive({
    from_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    to_date: new Date().toISOString().slice(0, 10),
    ledgerType: 'accounts-receivable-ledger',
    rows: [] as Array<Record<string, unknown>>,
    referenceNumber: '',
    pageCount: 1,
    loading: false,
    error: '',
});

useStateNotifications(state);

async function loadLedger() {
    state.loading = true;
    state.error = '';

    try {
        const url = api.reportUrl(`reports/${state.ledgerType}`, {
            from_date: state.from_date,
            to_date: state.to_date,
            branch_id: 1,
            format: 'json',
        });

        const response = await api.get<{
            reference_number: string;
            page_count: number;
            rows: Array<Record<string, unknown>>;
        }>(url);

        state.rows = response.rows;
        state.referenceNumber = response.reference_number;
        state.pageCount = response.page_count;
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load ledger report.';
    } finally {
        state.loading = false;
    }
}
</script>

<template>
    <Head title="Subsidiary Ledgers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard title="Ledger Filters">
                <form class="grid gap-3 md:grid-cols-4" @submit.prevent="loadLedger">
                    <select v-model="state.ledgerType" class="rounded border px-3 py-2 text-sm">
                        <optgroup label="Receivables">
                            <option value="accounts-receivable-ledger">Accounts Receivable Ledger</option>
                            <option value="customer-ledger">Customer Ledger</option>
                        </optgroup>
                        <optgroup label="Payables">
                            <option value="accounts-payable-ledger">Accounts Payable Ledger</option>
                            <option value="supplier-ledger">Supplier Ledger</option>
                        </optgroup>
                    </select>
                    <input v-model="state.from_date" type="date" class="rounded border px-3 py-2 text-sm" />
                    <input v-model="state.to_date" type="date" class="rounded border px-3 py-2 text-sm" />
                    <button type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground">
                        Load
                    </button>
                </form>
            </SectionCard>

            <SectionCard
                title="Ledger Result"
                :description="`Ref#: ${state.referenceNumber || '-'} | Pages: ${state.pageCount}`"
            >
                <div class="overflow-x-auto">
                    <table v-if="state.rows.length > 0" class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th v-for="(value, key) in state.rows[0]" :key="String(key)" class="px-2 py-2 text-left">
                                    {{ String(key).toUpperCase() }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in state.rows" :key="index" class="border-b">
                                <td v-for="(value, key) in row" :key="`${index}-${String(key)}`" class="px-2 py-2">
                                    {{ value }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-muted-foreground">Run a query to view subsidiary ledger rows.</p>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
        </div>
    </AppLayout>
</template>

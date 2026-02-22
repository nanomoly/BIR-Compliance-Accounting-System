<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CasReportFormat } from '@/types';

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Reports', href: '/cas/reports' },
];

const state = reactive({
    reportType: 'trial-balance',
    from_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    to_date: new Date().toISOString().slice(0, 10),
    period: 'monthly' as 'monthly' | 'quarterly' | 'annually',
    branch_id: 1,
    format: 'json' as CasReportFormat,
    rows: [] as Array<Record<string, unknown>>,
    referenceNumber: '',
    pageCount: 1,
    loading: false,
    error: '',
});

async function runReport() {
    state.loading = true;
    state.error = '';

    try {
        const endpoint = `reports/${state.reportType}`;

        if (state.format === 'json') {
            const url = api.reportUrl(endpoint, {
                from_date: state.from_date,
                to_date: state.to_date,
                branch_id: state.branch_id,
                period: state.period,
                format: 'json',
            });

            const response = await api.get<{
                reference_number: string;
                page_count: number;
                rows: Array<Record<string, unknown>>;
            }>(url);

            state.referenceNumber = response.reference_number;
            state.pageCount = response.page_count;
            state.rows = response.rows;
            return;
        }

        const downloadUrl = api.reportUrl(endpoint, {
            from_date: state.from_date,
            to_date: state.to_date,
            branch_id: state.branch_id,
            period: state.period,
            format: state.format,
        });

        window.open(downloadUrl, '_blank');
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to generate report.';
    } finally {
        state.loading = false;
    }
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="BIR-Mandated Reports"
                description="Generate monthly, quarterly, or annual reports in JSON, PDF, or Excel."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="runReport">
                    <select v-model="state.reportType" class="rounded border px-3 py-2 text-sm">
                        <optgroup label="Financial Statements">
                            <option value="trial-balance">Trial Balance</option>
                            <option value="balance-sheet">Balance Sheet</option>
                            <option value="income-statement">Income Statement</option>
                        </optgroup>
                        <optgroup label="Books">
                            <option value="journal-book">Journal Book</option>
                            <option value="general-ledger-book">General Ledger Book</option>
                        </optgroup>
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

                    <select v-model="state.period" class="rounded border px-3 py-2 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annually">Annually</option>
                    </select>
                    <select v-model="state.format" class="rounded border px-3 py-2 text-sm">
                        <option value="json">JSON Preview</option>
                        <option value="pdf">PDF Export</option>
                        <option value="excel">Excel Export</option>
                    </select>
                    <button type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground">
                        Generate
                    </button>
                </form>
            </SectionCard>

            <SectionCard
                v-if="state.format === 'json'"
                title="Report Preview"
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
                            <tr v-for="(row, rowIndex) in state.rows" :key="rowIndex" class="border-b">
                                <td v-for="(value, key) in row" :key="`${rowIndex}-${String(key)}`" class="px-2 py-2">
                                    {{ value }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-muted-foreground">No rows returned.</p>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Generating report...</p>
            <p v-if="state.error" class="text-sm text-destructive">{{ state.error }}</p>
        </div>
    </AppLayout>
</template>

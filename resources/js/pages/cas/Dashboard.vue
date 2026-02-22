<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookOpenText,
    Building2,
    FileUp,
    PenLine,
    Receipt,
    Users,
} from 'lucide-vue-next';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
];

const state = reactive({
    draftEntries: 0,
    postedEntries: 0,
    customerCount: 0,
    supplierCount: 0,
    invoiceCount: 0,
    accountCount: 0,
    branchCount: 0,
    totalEntries: 0,
    journalMonths: [] as string[],
    journalMonthCounts: [] as number[],
    invoiceMonths: [] as string[],
    invoiceMonthCounts: [] as number[],
    accountTypeLabels: [] as string[],
    accountTypeCounts: [] as number[],
    loading: false,
    error: '',
});

function monthKey(dateText: string): string {
    const date = new Date(dateText);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function monthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1, 1);

    return date.toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
}

function percentage(value: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.round((value / total) * 100);
}

function maxValue(values: number[]): number {
    if (values.length === 0) {
        return 1;
    }

    return Math.max(...values, 1);
}

function linePoints(values: number[]): string {
    if (values.length === 0) {
        return '';
    }

    const width = 320;
    const height = 140;
    const max = maxValue(values);

    return values
        .map((value, index) => {
            const x = values.length === 1 ? width / 2 : (index / (values.length - 1)) * width;
            const y = height - (value / max) * (height - 12) - 6;

            return `${Math.round(x)},${Math.round(y)}`;
        })
        .join(' ');
}

async function loadSummary() {
    state.loading = true;
    state.error = '';

    try {
        const [accountsResult, journalsResult, systemInfoResult, customersResult, suppliersResult, invoicesResult] = await Promise.allSettled([
            api.get<{ data: Array<{ type: string }> }>('/api/accounts?per_page=500'),
            api.get<{ data: Array<{ status: string; entry_date: string }> }>('/api/journal-entries?per_page=500'),
            api.get<{ company: unknown }>('/api/system-info'),
            api.get<{ data: Array<unknown> }>('/api/customers?per_page=500'),
            api.get<{ data: Array<unknown> }>('/api/suppliers?per_page=500'),
            api.get<{ data: Array<{ invoice_date: string }> }>('/api/e-invoices?per_page=500'),
        ]);

        const accounts = accountsResult.status === 'fulfilled' ? accountsResult.value.data : [];
        const journals = journalsResult.status === 'fulfilled' ? journalsResult.value.data : [];
        const company =
            systemInfoResult.status === 'fulfilled' ? systemInfoResult.value.company : null;
        const customers = customersResult.status === 'fulfilled' ? customersResult.value.data : [];
        const suppliers = suppliersResult.status === 'fulfilled' ? suppliersResult.value.data : [];
        const invoices = invoicesResult.status === 'fulfilled' ? invoicesResult.value.data : [];

        state.accountCount = accounts.length;
        state.postedEntries = journals.filter(
            (entry) => entry.status === 'posted',
        ).length;
        state.draftEntries = journals.filter(
            (entry) => entry.status === 'draft',
        ).length;
        state.customerCount = customers.length;
        state.supplierCount = suppliers.length;
        state.invoiceCount = invoices.length;
        state.branchCount = company ? 1 : 0;
        state.totalEntries = journals.length;

        const groupedMonths = journals.reduce<Record<string, number>>((acc, entry) => {
            const month = monthKey(entry.entry_date);
            acc[month] = (acc[month] ?? 0) + 1;

            return acc;
        }, {});
        const sortedMonths = Object.keys(groupedMonths).sort();
        state.journalMonths = sortedMonths.map(monthLabel);
        state.journalMonthCounts = sortedMonths.map((month) => groupedMonths[month]);

        const groupedInvoiceMonths = invoices.reduce<Record<string, number>>((acc, invoice) => {
            const month = monthKey(invoice.invoice_date);
            acc[month] = (acc[month] ?? 0) + 1;

            return acc;
        }, {});
        const sortedInvoiceMonths = Object.keys(groupedInvoiceMonths).sort();
        state.invoiceMonths = sortedInvoiceMonths.map(monthLabel);
        state.invoiceMonthCounts = sortedInvoiceMonths.map((month) => groupedInvoiceMonths[month]);

        const groupedTypes = accounts.reduce<Record<string, number>>((acc, account) => {
            acc[account.type] = (acc[account.type] ?? 0) + 1;

            return acc;
        }, {});
        state.accountTypeLabels = Object.keys(groupedTypes);
        state.accountTypeCounts = Object.values(groupedTypes);
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load dashboard data.';
    } finally {
        state.loading = false;
    }
}

onMounted(loadSummary);
</script>

<template>
    <Head title="CAS Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="grid gap-4 md:grid-cols-4 xl:grid-cols-6">
                <SectionCard title="Chart of Accounts">
                    <BookOpenText class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.accountCount }}</p>
                </SectionCard>
                <SectionCard title="Draft Journal Entries">
                    <PenLine class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.draftEntries }}</p>
                </SectionCard>
                <SectionCard title="Posted Journal Entries">
                    <FileUp class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.postedEntries }}</p>
                </SectionCard>
                <SectionCard title="Customers">
                    <Users class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.customerCount }}</p>
                </SectionCard>
                <SectionCard title="Suppliers">
                    <Users class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.supplierCount }}</p>
                </SectionCard>
                <SectionCard title="E-Invoices">
                    <Receipt class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.invoiceCount }}</p>
                </SectionCard>
                <SectionCard title="Branches">
                    <Building2 class="mb-2 h-5 w-5 text-muted-foreground" />
                    <p class="text-2xl font-semibold">{{ state.branchCount }}</p>
                </SectionCard>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <SectionCard
                    title="Journal Status Graph"
                    description="Distribution of draft versus posted journal entries."
                >
                    <div class="space-y-3 text-sm">
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span>Posted</span>
                                <span>{{ percentage(state.postedEntries, state.totalEntries) }}%</span>
                            </div>
                            <div class="h-3 rounded bg-muted">
                                <div
                                    class="h-3 rounded bg-primary"
                                    :style="{ width: `${percentage(state.postedEntries, state.totalEntries)}%` }"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span>Draft</span>
                                <span>{{ percentage(state.draftEntries, state.totalEntries) }}%</span>
                            </div>
                            <div class="h-3 rounded bg-muted">
                                <div
                                    class="h-3 rounded bg-amber-500"
                                    :style="{ width: `${percentage(state.draftEntries, state.totalEntries)}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Monthly Journal Line Trend"
                    description="Line graph of journal entries by month."
                >
                    <div v-if="state.journalMonthCounts.length > 0" class="space-y-2">
                        <svg viewBox="0 0 320 140" class="h-48 w-full">
                            <polyline
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                class="text-primary"
                                :points="linePoints(state.journalMonthCounts)"
                            />
                        </svg>
                        <div class="flex flex-wrap gap-2 text-[10px] text-muted-foreground">
                            <span v-for="label in state.journalMonths" :key="`journal-${label}`">{{ label }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        No journal trend data available.
                    </div>
                </SectionCard>
            </div>

            <SectionCard
                title="Monthly E-Invoice Line Trend"
                description="Line graph of sales and purchase e-invoice volume by month."
            >
                <div v-if="state.invoiceMonthCounts.length > 0" class="space-y-2">
                    <svg viewBox="0 0 320 140" class="h-48 w-full">
                        <polyline
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            class="text-primary"
                            :points="linePoints(state.invoiceMonthCounts)"
                        />
                    </svg>
                    <div class="flex flex-wrap gap-2 text-[10px] text-muted-foreground">
                        <span v-for="label in state.invoiceMonths" :key="`invoice-${label}`">{{ label }}</span>
                    </div>
                </div>
                <div v-else class="text-sm text-muted-foreground">
                    No e-invoice trend data available.
                </div>
            </SectionCard>

            <SectionCard
                title="Account Type Mix"
                description="Distribution of chart of account types."
            >
                <div class="grid gap-2 text-sm md:grid-cols-5">
                    <div
                        v-for="(label, index) in state.accountTypeLabels"
                        :key="`${label}-${index}`"
                        class="rounded border p-3"
                    >
                        <p class="text-xs uppercase text-muted-foreground">{{ label }}</p>
                        <p class="text-xl font-semibold">{{ state.accountTypeCounts[index] }}</p>
                    </div>
                </div>
            </SectionCard>

            <SectionCard
                title="BIR-CAS Modules"
                description="Navigate through all accounting and compliance modules."
            >
                <div class="grid gap-2 text-sm md:grid-cols-2 lg:grid-cols-3">
                    <a href="/cas/accounts" class="rounded border p-3 hover:bg-muted"
                        >Chart of Accounts</a
                    >
                    <a href="/cas/customers" class="rounded border p-3 hover:bg-muted"
                        >Customers (Receivables)</a
                    >
                    <a href="/cas/suppliers" class="rounded border p-3 hover:bg-muted"
                        >Suppliers (Payables)</a
                    >
                    <a href="/cas/journals" class="rounded border p-3 hover:bg-muted"
                        >Journals & General Ledger</a
                    >
                    <a href="/cas/e-invoicing" class="rounded border p-3 hover:bg-muted"
                        >E-Invoicing (Sales & Purchase)</a
                    >
                    <a href="/cas/reports" class="rounded border p-3 hover:bg-muted"
                        >BIR-Mandated Reports</a
                    >
                    <a href="/cas/ledgers" class="rounded border p-3 hover:bg-muted"
                        >Subsidiary Ledgers</a
                    >
                    <a href="/cas/backups" class="rounded border p-3 hover:bg-muted"
                        >Backup & Restore</a
                    >
                    <a href="/cas/system-info" class="rounded border p-3 hover:bg-muted"
                        >System Information</a
                    >
                    <a href="/cas/users" class="rounded border p-3 hover:bg-muted"
                        >System Users</a
                    >
                    <a href="/cas/user-access" class="rounded border p-3 hover:bg-muted"
                        >User Access Management</a
                    >
                    <a href="/cas/audit-trail" class="rounded border p-3 hover:bg-muted"
                        >Audit Trail Logs</a
                    >
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading dashboard data...
            </p>
            <p v-if="state.error" class="text-sm text-destructive">
                {{ state.error }}
            </p>
        </div>
    </AppLayout>
</template>

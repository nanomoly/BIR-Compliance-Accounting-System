<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive } from 'vue';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend);

const api = useCasApi();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
];

type InvoiceRow = {
    id: number;
    invoice_type: 'sales' | 'service' | 'purchase';
    status: 'draft' | 'issued' | 'cancelled';
    invoice_date: string;
    due_date: string | null;
    total_amount: number;
};

type ReceiptRow = {
    invoice_id: number;
    amount: number;
};

type BankAccountRow = {
    id: number;
    branch_id: number | null;
    current_balance: number;
};

type BankTransactionRow = {
    bank_account_id: number;
    transaction_date: string;
    transaction_type: 'debit' | 'credit';
    amount: number;
};

type BranchRow = {
    id: number;
    code: string;
    name: string;
};

const today = new Date();
const defaultFromMonth = new Date(today.getFullYear(), today.getMonth() - 5, 1).toISOString().slice(0, 7);
const defaultToMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 7);

const state = reactive({
    branches: [] as BranchRow[],
    selectedBranchId: 0,
    fromMonth: defaultFromMonth,
    toMonth: defaultToMonth,
    receivablesOpen: 0,
    receivablesOverdue: 0,
    payablesOpen: 0,
    payablesOverdue: 0,
    incomingThisMonth: 0,
    outgoingThisMonth: 0,
    profitThisMonth: 0,
    profitLossThisMonth: 0,
    accountBalance: 0,
    cashflowLabels: [] as string[],
    cashflowIncoming: [] as number[],
    cashflowOutgoing: [] as number[],
    cashflowProfit: [] as number[],
    loading: false,
    error: '',
});

useStateNotifications(state);

function monthKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');

    return `${year}-${month}`;
}

function parseDate(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function toAmount(value: number | string | null | undefined): number {
    return Number(value ?? 0);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function monthRange(fromMonth: string, toMonth: string): string[] {
    const from = parseDate(`${fromMonth}-01`);
    const to = parseDate(`${toMonth}-01`);

    if (!from || !to) {
        return [];
    }

    const start = from <= to ? from : to;
    const end = from <= to ? to : from;
    const labels: string[] = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), 1);

    while (cursor <= end && labels.length < 36) {
        labels.push(monthKey(cursor));
        cursor.setMonth(cursor.getMonth() + 1);
    }

    return labels;
}

const chartAbsMax = computed(() => {
    const values = [
        ...state.cashflowIncoming,
        ...state.cashflowOutgoing,
        ...state.cashflowProfit.map((value) => Math.abs(value)),
    ];

    if (values.length === 0) {
        return 1;
    }

    return Math.max(1, ...values);
});

const cashflowRows = computed(() => state.cashflowLabels.map((label, index) => ({
    label,
    incoming: state.cashflowIncoming[index] ?? 0,
    outgoing: state.cashflowOutgoing[index] ?? 0,
    profit: state.cashflowProfit[index] ?? 0,
})));

const cashflowBarData = computed<ChartData<'bar'>>(() => ({
    labels: state.cashflowLabels,
    datasets: [
        {
            type: 'bar',
            label: 'Incoming',
            data: state.cashflowIncoming,
            backgroundColor: 'rgba(34, 197, 94, 0.70)',
            borderColor: 'rgba(34, 197, 94, 1)',
            borderWidth: 1,
            borderRadius: 3,
            barThickness: 16,
        },
        {
            type: 'bar',
            label: 'Outgoing',
            data: state.cashflowOutgoing.map((value) => value * -1),
            backgroundColor: 'rgba(244, 63, 94, 0.70)',
            borderColor: 'rgba(244, 63, 94, 1)',
            borderWidth: 1,
            borderRadius: 3,
            barThickness: 16,
        },
        {
            type: 'line',
            label: 'Profit',
            data: state.cashflowProfit,
            borderColor: 'rgba(59, 130, 246, 1)',
            backgroundColor: 'rgba(59, 130, 246, 1)',
            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 1,
            pointRadius: 4,
            pointHoverRadius: 5,
            tension: 0.25,
            fill: false,
            borderWidth: 2,
        },
    ],
}));

const cashflowChartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            position: 'top',
            align: 'start',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                boxHeight: 8,
            },
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const label = context.dataset.label ?? '';
                    let value = Number(context.parsed.y ?? 0);

                    if (label === 'Outgoing') {
                        value = Math.abs(value);
                    }

                    return `${label}: ${formatCurrency(value)}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
        },
        y: {
            min: -chartAbsMax.value,
            max: chartAbsMax.value,
            ticks: {
                callback: (value) => formatCurrency(Math.abs(Number(value))),
            },
        },
    },
}));

const receivablesCurrentOpen = computed(() => Math.max(0, state.receivablesOpen - state.receivablesOverdue));
const payablesCurrentOpen = computed(() => Math.max(0, state.payablesOpen - state.payablesOverdue));

const receivablesOpenPercent = computed(() => {
    if (state.receivablesOpen <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, (receivablesCurrentOpen.value / state.receivablesOpen) * 100));
});

const receivablesOverduePercent = computed(() => {
    if (state.receivablesOpen <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, (state.receivablesOverdue / state.receivablesOpen) * 100));
});

const payablesOpenPercent = computed(() => {
    if (state.payablesOpen <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, (payablesCurrentOpen.value / state.payablesOpen) * 100));
});

const payablesOverduePercent = computed(() => {
    if (state.payablesOpen <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, (state.payablesOverdue / state.payablesOpen) * 100));
});

async function loadSummary() {
    state.loading = true;
    state.error = '';

    try {
        const [invoicesResult, receiptsResult, bankAccountsResult, bankTransactionsResult, branchesResult] = await Promise.allSettled([
            api.get<{ data: InvoiceRow[] }>('/api/e-invoices?per_page=1000'),
            api.get<{ data: ReceiptRow[] }>('/api/collections/receipts?per_page=1000'),
            api.get<{ data: BankAccountRow[] }>('/api/bank-accounts?per_page=500'),
            api.get<{ data: BankTransactionRow[] }>('/api/bank-transactions?per_page=1000'),
            api.get<{ data: BranchRow[] }>('/api/branches?per_page=500'),
        ]);

        const invoices = invoicesResult.status === 'fulfilled' ? invoicesResult.value.data : [];
        const receipts = receiptsResult.status === 'fulfilled' ? receiptsResult.value.data : [];
        const bankAccounts = bankAccountsResult.status === 'fulfilled' ? bankAccountsResult.value.data : [];
        const bankTransactions = bankTransactionsResult.status === 'fulfilled' ? bankTransactionsResult.value.data : [];
        state.branches = branchesResult.status === 'fulfilled' ? branchesResult.value.data : [];

        const filterToday = new Date();
        filterToday.setHours(0, 0, 0, 0);

        const fromBoundary = parseDate(`${state.fromMonth}-01`) ?? new Date(filterToday.getFullYear(), filterToday.getMonth() - 5, 1);
        const toMonthDate = parseDate(`${state.toMonth}-01`) ?? new Date(filterToday.getFullYear(), filterToday.getMonth(), 1);
        const toBoundary = new Date(toMonthDate.getFullYear(), toMonthDate.getMonth() + 1, 0);
        toBoundary.setHours(23, 59, 59, 999);

        const selectedBranchId = Number(state.selectedBranchId || 0);
        const filteredInvoices = invoices.filter((invoice) => {
            const invoiceDate = parseDate(invoice.invoice_date);
            const inRange = invoiceDate ? invoiceDate >= fromBoundary && invoiceDate <= toBoundary : false;
            const inBranch = selectedBranchId > 0 ? Number((invoice as { branch_id?: number }).branch_id ?? 0) === selectedBranchId : true;

            return inRange && inBranch;
        });

        const bankAccountById = new Map<number, BankAccountRow>();
        for (const account of bankAccounts) {
            bankAccountById.set(Number(account.id), account);
        }

        const filteredBankAccounts = bankAccounts.filter((account) => {
            if (selectedBranchId === 0) {
                return true;
            }

            return Number(account.branch_id ?? 0) === selectedBranchId;
        });

        const filteredBankTransactions = bankTransactions.filter((transaction) => {
            const transactionDate = parseDate(transaction.transaction_date);
            if (!transactionDate || transactionDate < fromBoundary || transactionDate > toBoundary) {
                return false;
            }

            if (selectedBranchId === 0) {
                return true;
            }

            const account = bankAccountById.get(Number(transaction.bank_account_id));
            return Number(account?.branch_id ?? 0) === selectedBranchId;
        });

        const filteredInvoiceIds = new Set(filteredInvoices.map((invoice) => Number(invoice.id)));
        const filteredReceipts = receipts.filter((receipt) => filteredInvoiceIds.has(Number(receipt.invoice_id)));

        const today = new Date(filterToday);
        today.setHours(0, 0, 0, 0);

        const paidByInvoice = new Map<number, number>();
        for (const receipt of filteredReceipts) {
            const invoiceId = Number(receipt.invoice_id ?? 0);
            const current = paidByInvoice.get(invoiceId) ?? 0;
            paidByInvoice.set(invoiceId, current + toAmount(receipt.amount));
        }

        let receivablesOpen = 0;
        let receivablesOverdue = 0;
        let payablesOpen = 0;
        let payablesOverdue = 0;

        let revenueInRange = 0;
        let expenseInRange = 0;

        for (const invoice of filteredInvoices) {
            if (invoice.status !== 'issued') {
                continue;
            }

            const dueDate = parseDate(invoice.due_date);
            const total = toAmount(invoice.total_amount);
            const isOverdue = dueDate !== null && dueDate < today;

            const invoiceDate = parseDate(invoice.invoice_date);

            if (invoice.invoice_type === 'sales' || invoice.invoice_type === 'service') {
                const paid = paidByInvoice.get(Number(invoice.id)) ?? 0;
                const open = Math.max(0, total - paid);

                receivablesOpen += open;
                if (open > 0 && isOverdue) {
                    receivablesOverdue += open;
                }

                if (invoiceDate !== null && invoiceDate >= fromBoundary && invoiceDate <= toBoundary) {
                    revenueInRange += total;
                }
            }

            if (invoice.invoice_type === 'purchase') {
                payablesOpen += total;
                if (isOverdue) {
                    payablesOverdue += total;
                }

                if (invoiceDate !== null && invoiceDate >= fromBoundary && invoiceDate <= toBoundary) {
                    expenseInRange += total;
                }
            }
        }

        state.receivablesOpen = Number(receivablesOpen.toFixed(2));
        state.receivablesOverdue = Number(receivablesOverdue.toFixed(2));
        state.payablesOpen = Number(payablesOpen.toFixed(2));
        state.payablesOverdue = Number(payablesOverdue.toFixed(2));
        state.profitLossThisMonth = Number((revenueInRange - expenseInRange).toFixed(2));

        state.accountBalance = Number(
            filteredBankAccounts.reduce((sum, account) => sum + toAmount(account.current_balance), 0).toFixed(2),
        );

        const monthKeys = monthRange(state.fromMonth, state.toMonth);

        const incomingByMonth = new Map<string, number>();
        const outgoingByMonth = new Map<string, number>();
        for (const key of monthKeys) {
            incomingByMonth.set(key, 0);
            outgoingByMonth.set(key, 0);
        }

        for (const transaction of filteredBankTransactions) {
            const transactionDate = parseDate(transaction.transaction_date);
            if (!transactionDate) {
                continue;
            }

            const key = monthKey(transactionDate);
            if (!incomingByMonth.has(key)) {
                continue;
            }

            const amount = toAmount(transaction.amount);
            if (transaction.transaction_type === 'credit') {
                incomingByMonth.set(key, (incomingByMonth.get(key) ?? 0) + amount);
            } else {
                outgoingByMonth.set(key, (outgoingByMonth.get(key) ?? 0) + amount);
            }
        }

        state.cashflowLabels = monthKeys;
        state.cashflowIncoming = monthKeys.map((key) => Number((incomingByMonth.get(key) ?? 0).toFixed(2)));
        state.cashflowOutgoing = monthKeys.map((key) => Number((outgoingByMonth.get(key) ?? 0).toFixed(2)));
        state.cashflowProfit = monthKeys.map((key, index) => Number((state.cashflowIncoming[index] - state.cashflowOutgoing[index]).toFixed(2)));

        state.incomingThisMonth = Number(state.cashflowIncoming.reduce((sum, value) => sum + value, 0).toFixed(2));
        state.outgoingThisMonth = Number(state.cashflowOutgoing.reduce((sum, value) => sum + value, 0).toFixed(2));
        state.profitThisMonth = Number((state.incomingThisMonth - state.outgoingThisMonth).toFixed(2));

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
            <SectionCard title="Filters" description="Branch and date range for dashboard metrics and chart.">
                <div class="grid gap-3 md:grid-cols-4">
                    <label class="grid gap-1 text-sm">
                        <span>Branch</span>
                        <select v-model.number="state.selectedBranchId" class="rounded border px-3 py-2 text-sm">
                            <option :value="0">All Branches</option>
                            <option v-for="branch in state.branches" :key="branch.id" :value="branch.id">
                                {{ branch.code }} - {{ branch.name }}
                            </option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>From Month</span>
                        <input v-model="state.fromMonth" type="month" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span>To Month</span>
                        <input v-model="state.toMonth" type="month" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <div class="flex items-end gap-2">
                        <button type="button" class="rounded border px-3 py-2 text-sm" :disabled="state.loading" @click="loadSummary">Apply</button>
                        <button
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            :disabled="state.loading"
                            @click="() => { state.selectedBranchId = 0; state.fromMonth = defaultFromMonth; state.toMonth = defaultToMonth; loadSummary(); }"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Receivables & Payables" description="Open and overdue positions based on active invoices.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded border p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-base font-semibold">Receivables</p>
                        </div>
                        <p class="text-xs text-muted-foreground">Amount that you're yet to receive from your customers.</p>
                        <div class="mt-4 border-t pt-4">
                            <p class="text-sm text-muted-foreground">Total unpaid invoices: <span class="font-semibold text-foreground">{{ formatCurrency(state.receivablesOpen) }}</span></p>
                            <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-muted">
                                <div class="flex h-full w-full">
                                    <div class="bg-amber-400" :style="{ width: `${receivablesOpenPercent}%` }" />
                                    <div class="bg-rose-400" :style="{ width: `${receivablesOverduePercent}%` }" />
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs uppercase text-amber-500">Open</p>
                                    <p class="text-lg font-semibold">{{ formatCurrency(receivablesCurrentOpen) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-rose-500">Overdue</p>
                                    <p class="text-lg font-semibold">{{ formatCurrency(state.receivablesOverdue) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded border p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-base font-semibold">Payables</p>
                        </div>
                        <p class="text-xs text-muted-foreground">Amount that you're yet to pay to your vendors.</p>
                        <div class="mt-4 border-t pt-4">
                            <p class="text-sm text-muted-foreground">Total unpaid bills: <span class="font-semibold text-foreground">{{ formatCurrency(state.payablesOpen) }}</span></p>
                            <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-muted">
                                <div class="flex h-full w-full">
                                    <div class="bg-amber-400" :style="{ width: `${payablesOpenPercent}%` }" />
                                    <div class="bg-rose-400" :style="{ width: `${payablesOverduePercent}%` }" />
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs uppercase text-amber-500">Open</p>
                                    <p class="text-lg font-semibold">{{ formatCurrency(payablesCurrentOpen) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-rose-500">Overdue</p>
                                    <p class="text-lg font-semibold">{{ formatCurrency(state.payablesOverdue) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Cash Flow Trend" description="Incoming, outgoing, and net cash flow within selected range.">
                <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <div class="rounded border p-3">
                        <div class="h-56 w-full">
                            <Bar :data="cashflowBarData" :options="cashflowChartOptions" />
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded border p-3">
                            <p class="text-xs uppercase text-muted-foreground">Incoming (Range)</p>
                            <p class="text-xl font-semibold">{{ formatCurrency(state.incomingThisMonth) }}</p>
                        </div>
                        <div class="rounded border p-3">
                            <p class="text-xs uppercase text-muted-foreground">Outgoing (Range)</p>
                            <p class="text-xl font-semibold">{{ formatCurrency(state.outgoingThisMonth) }}</p>
                        </div>
                        <div class="rounded border p-3">
                            <p class="text-xs uppercase text-muted-foreground">Profit (Range)</p>
                            <p class="text-xl font-semibold">{{ formatCurrency(state.profitThisMonth) }}</p>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <div class="grid gap-4 md:grid-cols-2">
                <SectionCard title="Profit & Loss" description="Issued revenue minus purchase expense in selected range.">
                    <p class="text-3xl font-semibold">{{ formatCurrency(state.profitLossThisMonth) }}</p>
                </SectionCard>

                <SectionCard title="Account Balance" description="Total current balance across active bank accounts.">
                    <p class="text-3xl font-semibold">{{ formatCurrency(state.accountBalance) }}</p>
                </SectionCard>
            </div>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading dashboard data...
            </p>
        </div>
    </AppLayout>
</template>

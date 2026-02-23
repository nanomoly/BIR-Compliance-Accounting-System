<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, toRef } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useQueryTabSync } from '@/composables/useQueryTabSync';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PayrollPeriod = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    pay_date: string;
    status: string;
};

type PayrollRun = {
    id: number;
    payroll_period_id: number;
    run_number: string;
    status: string;
    gross_total: number;
    deduction_total: number;
    net_total: number;
    period?: PayrollPeriod;
    approved_at?: string | null;
    posted_at?: string | null;
};

type PayrollRunLine = {
    id: number;
    employee?: {
        employee_no: string;
        first_name: string;
        last_name: string;
        department: string | null;
    };
    gross_amount: number;
    deduction_amount: number;
    net_amount: number;
    breakdown?: {
        sss_employee?: number;
        philhealth_employee?: number;
        pagibig_employee?: number;
        withholding_tax?: number;
    };
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Payroll', href: '/cas/payroll' },
];

const state = reactive({
    activeTab: 'period-setup',
    periods: [] as PayrollPeriod[],
    runs: [] as PayrollRun[],
    runLines: [] as PayrollRunLine[],
    selectedRunId: 0,
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    periodsPage: 1,
    periodsLastPage: 1,
    runsPage: 1,
    runsLastPage: 1,
    periodTotal: 0,
    runTotal: 0,
    loading: false,
    saving: false,
    processingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const { syncFromQuery } = useQueryTabSync(toRef(state, 'activeTab'), ['period-setup', 'run-input', 'transactions', 'register']);

const periodForm = reactive({
    name: '',
    start_date: '',
    end_date: '',
    pay_date: '',
});

const runForm = reactive({
    payroll_period_id: 0,
    preview_monthly_rate: 30000,
    sss_employee_rate: 0.045,
    sss_employee_cap: 1125,
    philhealth_rate: 0.05,
    philhealth_employee_cap: 2500,
    pagibig_employee_rate: 0.02,
    pagibig_employee_cap: 100,
    withholding_tax_rate: 0,
});

const selectedRun = computed(() => state.runs.find((run) => run.id === state.selectedRunId) ?? null);
const selectedPeriod = computed(() => state.periods.find((period) => period.id === runForm.payroll_period_id) ?? null);

const previewProrationDays = computed(() => {
    if (!selectedPeriod.value) {
        return 30;
    }

    const startDate = new Date(selectedPeriod.value.start_date);
    const endDate = new Date(selectedPeriod.value.end_date);

    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return 30;
    }

    const millis = endDate.getTime() - startDate.getTime();
    return Math.max(1, Math.floor(millis / 86_400_000) + 1);
});

const previewProrationFactor = computed(() => previewProrationDays.value / 30);
const previewGrossAmount = computed(() => Number((Number(runForm.preview_monthly_rate || 0) * previewProrationFactor.value).toFixed(2)));
const previewSssEmployee = computed(() => Number(Math.min(previewGrossAmount.value * Number(runForm.sss_employee_rate || 0), Number(runForm.sss_employee_cap || 0)).toFixed(2)));
const previewPhilhealthEmployee = computed(() => Number(Math.min((previewGrossAmount.value * Number(runForm.philhealth_rate || 0)) / 2, Number(runForm.philhealth_employee_cap || 0)).toFixed(2)));
const previewPagibigEmployee = computed(() => Number(Math.min(previewGrossAmount.value * Number(runForm.pagibig_employee_rate || 0), Number(runForm.pagibig_employee_cap || 0)).toFixed(2)));
const previewTaxablePay = computed(() => Number(Math.max(0, previewGrossAmount.value - (previewSssEmployee.value + previewPhilhealthEmployee.value + previewPagibigEmployee.value)).toFixed(2)));
const previewWithholdingTax = computed(() => Number((previewTaxablePay.value * Number(runForm.withholding_tax_rate || 0)).toFixed(2)));
const previewTotalDeduction = computed(() => Number((previewSssEmployee.value + previewPhilhealthEmployee.value + previewPagibigEmployee.value + previewWithholdingTax.value).toFixed(2)));
const previewNetAmount = computed(() => Number((previewGrossAmount.value - previewTotalDeduction.value).toFixed(2)));

function formatPhDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value.length >= 10 ? value.slice(0, 10) : value;
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);

    const year = parts.find((part) => part.type === 'year')?.value;
    const month = parts.find((part) => part.type === 'month')?.value;
    const day = parts.find((part) => part.type === 'day')?.value;

    if (!year || !month || !day) {
        return value.length >= 10 ? value.slice(0, 10) : value;
    }

    return `${year}-${month}-${day}`;
}

function exportPayrollRuns() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/payroll-runs?${query.toString()}`, '_blank');
}

async function loadPeriods(page = 1) {
    const response = await api.get<{
        data: PayrollPeriod[];
        current_page: number;
        last_page: number;
        total: number;
    }>(`/api/payroll-periods?per_page=10&page=${page}`);

    state.periods = response.data;
    state.periodsPage = response.current_page;
    state.periodsLastPage = response.last_page;
    state.periodTotal = response.total;

    if (!runForm.payroll_period_id && state.periods.length > 0) {
        runForm.payroll_period_id = state.periods[0].id;
    }
}

async function loadRuns(page = 1) {
    const response = await api.get<{
        data: PayrollRun[];
        current_page: number;
        last_page: number;
        total: number;
    }>(`/api/payroll-runs?per_page=10&page=${page}`);

    state.runs = response.data;
    state.runsPage = response.current_page;
    state.runsLastPage = response.last_page;
    state.runTotal = response.total;

    if (!state.selectedRunId && state.runs.length > 0) {
        state.selectedRunId = state.runs[0].id;
        await loadRunDetails(state.selectedRunId);
    }
}

async function loadRunDetails(runId: number) {
    state.selectedRunId = runId;

    const run = await api.get<PayrollRun & { lines: PayrollRunLine[] }>(`/api/payroll-runs/${runId}`);
    state.runLines = run.lines ?? [];
}

async function createPeriod() {
    if (!can('payroll.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/payroll-periods', {
            name: periodForm.name,
            start_date: periodForm.start_date,
            end_date: periodForm.end_date,
            pay_date: periodForm.pay_date,
        });

        state.success = 'Payroll period created.';
        periodForm.name = '';
        periodForm.start_date = '';
        periodForm.end_date = '';
        periodForm.pay_date = '';
        await loadPeriods(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to create payroll period.';
    } finally {
        state.saving = false;
    }
}

async function generateRun() {
    if (!can('payroll.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/payroll-runs/generate', {
            payroll_period_id: runForm.payroll_period_id,
            sss_employee_rate: runForm.sss_employee_rate,
            sss_employee_cap: runForm.sss_employee_cap,
            philhealth_rate: runForm.philhealth_rate,
            philhealth_employee_cap: runForm.philhealth_employee_cap,
            pagibig_employee_rate: runForm.pagibig_employee_rate,
            pagibig_employee_cap: runForm.pagibig_employee_cap,
            withholding_tax_rate: runForm.withholding_tax_rate,
        });

        state.success = 'Payroll run generated.';
        await loadRuns(1);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to generate payroll run.';
    } finally {
        state.saving = false;
    }
}

async function approveRun(id: number) {
    if (!can('payroll.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/payroll-runs/${id}/approve`, {});
        state.success = 'Payroll run approved.';
        await loadRuns(state.runsPage);
        await loadRunDetails(id);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to approve payroll run.';
    } finally {
        state.processingId = 0;
    }
}

async function postRun(id: number) {
    if (!can('payroll.update')) {
        return;
    }

    state.processingId = id;
    state.error = '';
    state.success = '';

    try {
        await api.post(`/api/payroll-runs/${id}/post`, {});
        state.success = 'Payroll run posted and period closed.';
        await Promise.all([loadRuns(state.runsPage), loadPeriods(state.periodsPage)]);
        await loadRunDetails(id);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to post payroll run.';
    } finally {
        state.processingId = 0;
    }
}

onMounted(async () => {
    syncFromQuery();

    state.loading = true;
    state.error = '';

    try {
        await Promise.all([loadPeriods(), loadRuns()]);
    } catch (error) {
        state.error = error instanceof Error ? error.message : 'Failed to initialize payroll module.';
    } finally {
        state.loading = false;
    }
});
</script>

<template>
    <Head title="Payroll" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'period-setup' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'period-setup'">Period Setup</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'run-input' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'run-input'">Run Input</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'transactions' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'transactions'">Transactions</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" :class="state.activeTab === 'register' ? 'bg-primary text-primary-foreground' : ''" @click="state.activeTab = 'register'">Payroll Register</button>
            </div>

            <SectionCard v-if="state.activeTab === 'period-setup'" title="Create Payroll Period" description="Define payroll cut-off and pay date.">
                <form class="grid gap-3 md:grid-cols-4" @submit.prevent="createPeriod">
                    <label class="grid gap-1 text-sm"><span>Period Name</span><input v-model="periodForm.name" required class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Start Date</span><input v-model="periodForm.start_date" required type="date" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>End Date</span><input v-model="periodForm.end_date" required type="date" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Pay Date</span><input v-model="periodForm.pay_date" required type="date" class="rounded border px-3 py-2 text-sm" /></label>
                    <button v-if="can('payroll.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                        {{ state.saving ? 'Saving...' : 'Create Period' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'run-input'" title="Generate Payroll Run" description="Generate PH payroll with SSS, PhilHealth, Pag-IBIG, and withholding tax settings.">
                <form class="grid gap-3 md:grid-cols-4" @submit.prevent="generateRun">
                    <label class="grid gap-1 text-sm"><span>Payroll Period</span><select v-model.number="runForm.payroll_period_id" required class="rounded border px-3 py-2 text-sm">
                        <option :value="0" disabled>Select period</option>
                        <option v-for="period in state.periods" :key="period.id" :value="period.id">
                            {{ period.name }} ({{ formatPhDateOnly(period.start_date) }} to {{ formatPhDateOnly(period.end_date) }})
                        </option>
                    </select></label>
                    <label class="grid gap-1 text-sm"><span>SSS Employee Rate</span><input
                        v-model.number="runForm.sss_employee_rate"
                        type="number"
                        min="0"
                        max="1"
                        step="0.001"
                        class="rounded border px-3 py-2 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm"><span>SSS Cap</span><input v-model.number="runForm.sss_employee_cap" type="number" min="0" step="0.01" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>PhilHealth Total Rate</span><input
                        v-model.number="runForm.philhealth_rate"
                        type="number"
                        min="0"
                        max="1"
                        step="0.001"
                        class="rounded border px-3 py-2 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm"><span>PhilHealth Employee Cap</span><input v-model.number="runForm.philhealth_employee_cap" type="number" min="0" step="0.01" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Pag-IBIG Employee Rate</span><input
                        v-model.number="runForm.pagibig_employee_rate"
                        type="number"
                        min="0"
                        max="1"
                        step="0.001"
                        class="rounded border px-3 py-2 text-sm"
                    /></label>
                    <label class="grid gap-1 text-sm"><span>Pag-IBIG Cap</span><input v-model.number="runForm.pagibig_employee_cap" type="number" min="0" step="0.01" class="rounded border px-3 py-2 text-sm" /></label>
                    <label class="grid gap-1 text-sm"><span>Withholding Tax Rate</span><input
                        v-model.number="runForm.withholding_tax_rate"
                        type="number"
                        min="0"
                        max="1"
                        step="0.001"
                        class="rounded border px-3 py-2 text-sm"
                    /></label>
                    <button v-if="can('payroll.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" :disabled="state.saving">
                        {{ state.saving ? 'Generating...' : 'Generate Run' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'run-input'" title="Payroll Calculator Preview" description="Sample single-employee computation using current period and deduction settings.">
                <div class="grid gap-3 md:grid-cols-4">
                    <label class="grid gap-1 text-sm">
                        <span>Sample Monthly Rate</span>
                        <input v-model.number="runForm.preview_monthly_rate" type="number" min="0" step="0.01" class="rounded border px-3 py-2 text-sm" />
                    </label>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Proration Days</p>
                        <p class="font-medium">{{ previewProrationDays }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="flex items-center gap-1 text-xs uppercase text-muted-foreground">
                            <span>Proration Factor</span>
                            <span
                                class="inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full border text-[10px] normal-case"
                                title="Proration Factor = Proration Days ÷ 30. It adjusts monthly salary based on the selected payroll period length."
                                aria-label="Proration factor info"
                            >
                                i
                            </span>
                        </p>
                        <p class="font-medium">{{ previewProrationFactor.toFixed(4) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Gross</p>
                        <p class="font-medium">{{ formatAmount(previewGrossAmount) }}</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">SSS</p>
                        <p class="font-medium">{{ formatAmount(previewSssEmployee) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">PhilHealth</p>
                        <p class="font-medium">{{ formatAmount(previewPhilhealthEmployee) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Pag-IBIG</p>
                        <p class="font-medium">{{ formatAmount(previewPagibigEmployee) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Taxable Pay</p>
                        <p class="font-medium">{{ formatAmount(previewTaxablePay) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Withholding Tax</p>
                        <p class="font-medium">{{ formatAmount(previewWithholdingTax) }}</p>
                    </div>
                    <div class="rounded border px-3 py-2 text-sm">
                        <p class="text-xs uppercase text-muted-foreground">Total Deduction</p>
                        <p class="font-medium">{{ formatAmount(previewTotalDeduction) }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded border px-3 py-3 text-sm">
                    <p class="text-xs uppercase text-muted-foreground">Estimated Net Pay</p>
                    <p class="text-xl font-semibold">{{ formatAmount(previewNetAmount) }}</p>
                </div>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'transactions'" title="Payroll Transactions">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total runs: {{ state.runTotal }}</p>
                    <div class="flex items-end gap-2">
                        <label class="grid gap-1 text-xs"><span>From</span><input v-model="state.exportFromDate" type="date" class="rounded border px-2 py-2 text-sm" /></label>
                        <label class="grid gap-1 text-xs"><span>To</span><input v-model="state.exportToDate" type="date" class="rounded border px-2 py-2 text-sm" /></label>
                        <button v-if="can('payroll.view')" type="button" class="self-end rounded border px-3 py-2 text-sm" @click="exportPayrollRuns">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Run #</th>
                                <th class="px-2 py-2 text-left">Period</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Gross</th>
                                <th class="px-2 py-2 text-left">Deductions</th>
                                <th class="px-2 py-2 text-left">Net</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="run in state.runs" :key="run.id" class="border-b">
                                <td class="px-2 py-2">
                                    <button type="button" class="text-left underline" @click="loadRunDetails(run.id)">
                                        {{ run.run_number }}
                                    </button>
                                </td>
                                <td class="px-2 py-2">{{ run.period?.name ?? '-' }}</td>
                                <td class="px-2 py-2 uppercase">{{ run.status }}</td>
                                <td class="px-2 py-2">{{ formatAmount(run.gross_total) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(run.deduction_total) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(run.net_total) }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="can('payroll.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === run.id || run.status !== 'draft'"
                                            @click="approveRun(run.id)"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            v-if="can('payroll.update')"
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            :disabled="state.processingId === run.id || run.status !== 'approved'"
                                            @click="postRun(run.id)"
                                        >
                                            Post
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.runsPage <= 1" @click="loadRuns(state.runsPage - 1)">Previous</button>
                    <span class="text-sm text-muted-foreground">Page {{ state.runsPage }} of {{ state.runsLastPage }}</span>
                    <button type="button" class="rounded border px-3 py-1 text-sm" :disabled="state.runsPage >= state.runsLastPage" @click="loadRuns(state.runsPage + 1)">Next</button>
                </div>
            </SectionCard>

            <SectionCard v-if="state.activeTab === 'register'" title="Payroll Register" :description="selectedRun ? `Lines for ${selectedRun.run_number}` : 'Select a payroll run to view lines.'">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Employee No</th>
                                <th class="px-2 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-left">Department</th>
                                <th class="px-2 py-2 text-left">Gross</th>
                                <th class="px-2 py-2 text-left">SSS</th>
                                <th class="px-2 py-2 text-left">PhilHealth</th>
                                <th class="px-2 py-2 text-left">Pag-IBIG</th>
                                <th class="px-2 py-2 text-left">W/Tax</th>
                                <th class="px-2 py-2 text-left">Deductions</th>
                                <th class="px-2 py-2 text-left">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in state.runLines" :key="line.id" class="border-b">
                                <td class="px-2 py-2">{{ line.employee?.employee_no ?? '-' }}</td>
                                <td class="px-2 py-2">{{ line.employee?.first_name }} {{ line.employee?.last_name }}</td>
                                <td class="px-2 py-2">{{ line.employee?.department ?? '-' }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.gross_amount) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.breakdown?.sss_employee) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.breakdown?.philhealth_employee) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.breakdown?.pagibig_employee) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.breakdown?.withholding_tax) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.deduction_amount) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(line.net_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading payroll data...</p>
        </div>
    </AppLayout>
</template>

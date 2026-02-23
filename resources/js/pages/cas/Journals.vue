<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import { formatAmount, formatPhDateOnly } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    AccountRow,
    BreadcrumbItem,
    JournalPayload,
    PaginatedResponse,
} from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Journals', href: '/cas/journals' },
];

const state = reactive({
    accounts: [] as AccountRow[],
    entries: [] as Array<any>,
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

const form = reactive<JournalPayload>({
    branch_id: 1,
    journal_type: 'general',
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    reference_no: '',
    lines: [
        { account_id: 0, debit: 0, credit: 0, particulars: '' },
        { account_id: 0, debit: 0, credit: 0, particulars: '' },
    ],
});

function addLine() {
    form.lines.push({ account_id: 0, debit: 0, credit: 0, particulars: '' });
}

function removeLine(index: number) {
    if (form.lines.length > 2) {
        form.lines.splice(index, 1);
    }
}

async function loadData(page = 1) {
    state.loading = true;

    try {
        const [accounts, entries] = await Promise.all([
            api.get<PaginatedResponse<AccountRow>>('/api/accounts?per_page=200'),
            api.get<PaginatedResponse<any>>(`/api/journal-entries?per_page=${state.perPage}&page=${page}`),
        ]);

        state.accounts = accounts.data;
        state.entries = entries.data;
        state.currentPage = entries.current_page;
        state.lastPage = entries.last_page;
        state.perPage = entries.per_page;
        state.total = entries.total;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load journals.';
    } finally {
        state.loading = false;
    }
}

function exportJournals() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/journals?${query.toString()}`, '_blank');
}

async function createEntry() {
    if (!can('journals.create')) {
        return;
    }

    state.error = '';
    state.success = '';

    try {
        await api.post('/api/journal-entries', {
            ...form,
            reference_no: form.reference_no || null,
            lines: form.lines,
        });
        state.success = 'Journal entry created.';
        form.description = '';
        form.reference_no = '';
        form.lines = [
            { account_id: 0, debit: 0, credit: 0, particulars: '' },
            { account_id: 0, debit: 0, credit: 0, particulars: '' },
        ];
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to create journal entry.';
    }
}

async function postEntry(entryId: number) {
    state.error = '';

    try {
        await api.post(`/api/journal-entries/${entryId}/post`);
        state.success = 'Entry posted.';
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to post entry.';
    }
}

async function reverseEntry(entryId: number) {
    state.error = '';

    try {
        await api.post(`/api/journal-entries/${entryId}/reverse`);
        state.success = 'Reversal entry created.';
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to reverse entry.';
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Journals" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Create Journal Entry"
                description="Debit and credit totals must match before posting."
            >
                <form class="grid gap-3" @submit.prevent="createEntry">
                    <div class="grid gap-3 md:grid-cols-4">
                        <select v-model="form.journal_type" class="rounded border px-3 py-2 text-sm">
                            <option value="general">General Journal</option>
                            <option value="sales">Sales Journal</option>
                            <option value="purchase">Purchase Journal</option>
                            <option value="cash_receipts">Cash Receipts Journal</option>
                            <option value="cash_disbursements">Cash Disbursements Journal</option>
                        </select>
                        <input v-model="form.entry_date" type="date" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.reference_no" placeholder="Reference No" class="rounded border px-3 py-2 text-sm" />
                        <input v-model="form.description" required placeholder="Description" class="rounded border px-3 py-2 text-sm" />
                    </div>

                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-2 py-2 text-left">Account</th>
                                    <th class="px-2 py-2 text-left">Debit</th>
                                    <th class="px-2 py-2 text-left">Credit</th>
                                    <th class="px-2 py-2 text-left">Particulars</th>
                                    <th class="px-2 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-b">
                                    <td class="px-2 py-2">
                                        <select v-model.number="line.account_id" required class="w-full rounded border px-2 py-1">
                                            <option :value="0" disabled>Select account</option>
                                            <option v-for="account in state.accounts" :key="account.id" :value="account.id">
                                                {{ account.code }} - {{ account.name }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.debit" type="number" step="0.01" min="0" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="line.credit" type="number" step="0.01" min="0" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model="line.particulars" class="w-full rounded border px-2 py-1" />
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
                        <button v-if="can('journals.create')" type="submit" class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground">Save Draft</button>
                    </div>
                </form>
            </SectionCard>

            <SectionCard title="Journal Entries">
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
                        <button
                            v-if="can('journals.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportJournals"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Entry #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-left">Journal</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-left">Debit</th>
                                <th class="px-2 py-2 text-left">Credit</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in state.entries" :key="entry.id" class="border-b">
                                <td class="px-2 py-2">{{ entry.entry_number }}</td>
                                <td class="px-2 py-2">{{ formatPhDateOnly(entry.entry_date) }}</td>
                                <td class="px-2 py-2">{{ entry.journal_type }}</td>
                                <td class="px-2 py-2 uppercase">{{ entry.status }}</td>
                                <td class="px-2 py-2">{{ formatAmount(entry.total_debit) }}</td>
                                <td class="px-2 py-2">{{ formatAmount(entry.total_credit) }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <button
                                            v-if="entry.status === 'draft'"
                                            class="rounded border px-2 py-1 text-xs"
                                            @click="postEntry(entry.id)"
                                        >
                                            Post
                                        </button>
                                        <button
                                            v-if="entry.status === 'posted'"
                                            class="rounded border px-2 py-1 text-xs"
                                            @click="reverseEntry(entry.id)"
                                        >
                                            Reverse
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage <= 1"
                        @click="loadData(state.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-muted-foreground">
                        Page {{ state.currentPage }} of {{ state.lastPage }}
                    </span>
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage >= state.lastPage"
                        @click="loadData(state.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">Loading...</p>
        </div>
    </AppLayout>
</template>
